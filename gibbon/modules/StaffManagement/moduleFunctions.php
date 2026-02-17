<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuibers and the Gibbon community (https://gibbonedu.org/about/)
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
 * Staff Management Module Functions
 *
 * Helper functions for the Staff Management module including ratio calculations,
 * formatting utilities, and Quebec compliance helpers.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

// ========================================
// QUEBEC RATIO CONSTANTS
// ========================================

/**
 * Quebec staff-to-child ratios by age group.
 * Values represent the maximum number of children per staff member.
 */
define('STAFF_RATIO_INFANT', 5);      // 0-18 months
define('STAFF_RATIO_TODDLER', 8);     // 18-36 months
define('STAFF_RATIO_PRESCHOOL', 10);  // 36-60 months
define('STAFF_RATIO_SCHOOL_AGE', 20); // 60+ months

/**
 * Age group labels with age ranges.
 */
define('STAFF_AGE_GROUPS', [
    'Infant' => ['label' => 'Infant', 'ageRange' => '0-18 months', 'ratio' => STAFF_RATIO_INFANT, 'color' => 'blue'],
    'Toddler' => ['label' => 'Toddler', 'ageRange' => '18-36 months', 'ratio' => STAFF_RATIO_TODDLER, 'color' => 'green'],
    'Preschool' => ['label' => 'Preschool', 'ageRange' => '36-60 months', 'ratio' => STAFF_RATIO_PRESCHOOL, 'color' => 'purple'],
    'School Age' => ['label' => 'School Age', 'ageRange' => '60+ months', 'ratio' => STAFF_RATIO_SCHOOL_AGE, 'color' => 'indigo'],
]);

// ========================================
// RATIO CALCULATION FUNCTIONS
// ========================================

/**
 * Get the Quebec required ratio for an age group.
 *
 * @param string $ageGroup Age group name (Infant, Toddler, Preschool, School Age)
 * @return int Required ratio (children per staff)
 */
function getRequiredRatioForAgeGroup($ageGroup)
{
    $ratios = [
        'Infant' => STAFF_RATIO_INFANT,
        'Toddler' => STAFF_RATIO_TODDLER,
        'Preschool' => STAFF_RATIO_PRESCHOOL,
        'School Age' => STAFF_RATIO_SCHOOL_AGE,
    ];

    return $ratios[$ageGroup] ?? STAFF_RATIO_PRESCHOOL;
}

/**
 * Calculate the actual staff-to-child ratio.
 *
 * @param int $staffCount Number of staff members
 * @param int $childCount Number of children
 * @return float Actual ratio (children per staff)
 */
function calculateActualRatio($staffCount, $childCount)
{
    if ($staffCount <= 0 || $childCount <= 0) {
        return 0;
    }

    return $childCount / $staffCount;
}

/**
 * Check if a ratio is compliant with Quebec requirements.
 *
 * @param string $ageGroup Age group name
 * @param int $staffCount Number of staff members
 * @param int $childCount Number of children
 * @return bool True if compliant
 */
function isRatioCompliant($ageGroup, $staffCount, $childCount)
{
    if ($childCount <= 0) {
        return true; // No children means compliant
    }

    if ($staffCount <= 0) {
        return false; // Children with no staff is non-compliant
    }

    $actualRatio = calculateActualRatio($staffCount, $childCount);
    $requiredRatio = getRequiredRatioForAgeGroup($ageGroup);

    return $actualRatio <= $requiredRatio;
}

/**
 * Calculate the compliance percentage.
 * 100% means at capacity, >100% means over capacity (non-compliant).
 *
 * @param string $ageGroup Age group name
 * @param int $staffCount Number of staff members
 * @param int $childCount Number of children
 * @return float Compliance percentage
 */
function calculateCompliancePercent($ageGroup, $staffCount, $childCount)
{
    if ($staffCount <= 0 || $childCount <= 0) {
        return 0;
    }

    $requiredRatio = getRequiredRatioForAgeGroup($ageGroup);
    $maxCapacity = $staffCount * $requiredRatio;

    return ($childCount / $maxCapacity) * 100;
}

/**
 * Calculate how many additional children can be added while remaining compliant.
 *
 * @param string $ageGroup Age group name
 * @param int $staffCount Number of staff members
 * @param int $childCount Number of children
 * @return int Additional capacity (negative if over capacity)
 */
function calculateAdditionalCapacity($ageGroup, $staffCount, $childCount)
{
    if ($staffCount <= 0) {
        return 0;
    }

    $requiredRatio = getRequiredRatioForAgeGroup($ageGroup);
    $maxCapacity = $staffCount * $requiredRatio;

    return $maxCapacity - $childCount;
}

/**
 * Calculate how many additional staff are needed to become compliant.
 *
 * @param string $ageGroup Age group name
 * @param int $staffCount Number of staff members
 * @param int $childCount Number of children
 * @return int Additional staff needed (0 if already compliant)
 */
function calculateStaffNeeded($ageGroup, $staffCount, $childCount)
{
    if ($childCount <= 0) {
        return 0;
    }

    $requiredRatio = getRequiredRatioForAgeGroup($ageGroup);
    $staffNeeded = ceil($childCount / $requiredRatio);

    return max(0, $staffNeeded - $staffCount);
}

// ========================================
// RATIO FORMATTING FUNCTIONS
// ========================================

/**
 * Format a ratio for display (e.g., "1:5").
 *
 * @param float $ratio The ratio value
 * @param int $precision Decimal precision
 * @return string Formatted ratio string
 */
function formatRatio($ratio, $precision = 1)
{
    if ($ratio <= 0) {
        return __('N/A');
    }

    return '1:' . round($ratio, $precision);
}

/**
 * Format a ratio from staff and child counts.
 *
 * @param int $staffCount Number of staff members
 * @param int $childCount Number of children
 * @param int $precision Decimal precision
 * @return string Formatted ratio string
 */
function formatRatioFromCounts($staffCount, $childCount, $precision = 1)
{
    if ($staffCount <= 0) {
        return __('No Staff');
    }

    if ($childCount <= 0) {
        return __('No Children');
    }

    $ratio = calculateActualRatio($staffCount, $childCount);
    return formatRatio($ratio, $precision);
}

/**
 * Get the compliance status label with HTML styling.
 *
 * @param bool $isCompliant Whether the ratio is compliant
 * @param float $compliancePercent Compliance percentage (optional)
 * @param bool $hasChildren Whether there are children present
 * @return string HTML status label
 */
function getRatioComplianceLabel($isCompliant, $compliancePercent = 0, $hasChildren = true)
{
    if (!$hasChildren) {
        return '<span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-sm">&#8212; ' . __('No Children') . '</span>';
    }

    if ($isCompliant) {
        if ($compliancePercent >= 90) {
            return '<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-sm font-semibold">&#9888; ' . __('Near Capacity') . '</span>';
        }
        return '<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm font-semibold">&#10003; ' . __('Compliant') . '</span>';
    }

    return '<span class="bg-red-100 text-red-800 px-2 py-1 rounded text-sm font-semibold">&#10007; ' . __('Non-Compliant') . '</span>';
}

/**
 * Get CSS class for compliance rate display.
 *
 * @param float $complianceRate Compliance rate percentage
 * @return string CSS class string
 */
function getComplianceRateClass($complianceRate)
{
    if ($complianceRate >= 95) {
        return 'text-green-600';
    } elseif ($complianceRate >= 80) {
        return 'text-orange-500';
    }
    return 'text-red-600';
}

/**
 * Get CSS class for capacity display.
 *
 * @param float $capacityPercent Capacity percentage
 * @return string CSS class string for background
 */
function getCapacityColorClass($capacityPercent)
{
    if ($capacityPercent >= 100) {
        return 'bg-red-500';
    } elseif ($capacityPercent >= 90) {
        return 'bg-yellow-500';
    }
    return 'bg-green-500';
}

// ========================================
// AGE GROUP HELPER FUNCTIONS
// ========================================

/**
 * Get age group information.
 *
 * @param string $ageGroup Age group name
 * @return array Age group info (label, ageRange, ratio, color)
 */
function getAgeGroupInfo($ageGroup)
{
    $groups = STAFF_AGE_GROUPS;
    return $groups[$ageGroup] ?? ['label' => $ageGroup, 'ageRange' => '', 'ratio' => 10, 'color' => 'gray'];
}

/**
 * Get all age group names.
 *
 * @return array List of age group names
 */
function getAgeGroupList()
{
    return array_keys(STAFF_AGE_GROUPS);
}

/**
 * Get the age group for a child based on age in months.
 *
 * @param int $ageInMonths Child's age in months
 * @return string Age group name
 */
function getAgeGroupFromMonths($ageInMonths)
{
    if ($ageInMonths < 18) {
        return 'Infant';
    } elseif ($ageInMonths < 36) {
        return 'Toddler';
    } elseif ($ageInMonths < 60) {
        return 'Preschool';
    }
    return 'School Age';
}

/**
 * Get the translated age group label.
 *
 * @param string $ageGroup Age group name
 * @return string Translated label
 */
function getAgeGroupLabel($ageGroup)
{
    $info = getAgeGroupInfo($ageGroup);
    return __($info['label']);
}

/**
 * Get the age range description for an age group.
 *
 * @param string $ageGroup Age group name
 * @return string Age range (e.g., "0-18 months")
 */
function getAgeGroupRange($ageGroup)
{
    $info = getAgeGroupInfo($ageGroup);
    return __($info['ageRange']);
}

// ========================================
// TIME AND HOURS FORMATTING
// ========================================

/**
 * Format minutes as hours and minutes display.
 *
 * @param int $totalMinutes Total minutes
 * @param bool $short Use short format (e.g., "8h 30m" vs "8 hours 30 minutes")
 * @return string Formatted time string
 */
function formatHoursMinutes($totalMinutes, $short = true)
{
    if ($totalMinutes <= 0) {
        return $short ? '0m' : __('0 minutes');
    }

    $hours = floor($totalMinutes / 60);
    $minutes = $totalMinutes % 60;

    if ($short) {
        if ($hours > 0 && $minutes > 0) {
            return $hours . 'h ' . $minutes . 'm';
        } elseif ($hours > 0) {
            return $hours . 'h';
        }
        return $minutes . 'm';
    }

    // Long format
    $parts = [];
    if ($hours > 0) {
        $parts[] = $hours . ' ' . ($hours === 1 ? __('hour') : __('hours'));
    }
    if ($minutes > 0) {
        $parts[] = $minutes . ' ' . ($minutes === 1 ? __('minute') : __('minutes'));
    }

    return implode(' ', $parts);
}

/**
 * Format decimal hours for display.
 *
 * @param float $decimalHours Hours in decimal format
 * @param int $precision Decimal precision
 * @return string Formatted hours string
 */
function formatDecimalHours($decimalHours, $precision = 2)
{
    if ($decimalHours <= 0) {
        return '0.00';
    }

    return number_format($decimalHours, $precision);
}

/**
 * Convert decimal hours to hours and minutes.
 *
 * @param float $decimalHours Hours in decimal format
 * @return array ['hours' => int, 'minutes' => int]
 */
function decimalHoursToHoursMinutes($decimalHours)
{
    $totalMinutes = round($decimalHours * 60);
    return [
        'hours' => floor($totalMinutes / 60),
        'minutes' => $totalMinutes % 60,
    ];
}

// ========================================
// STATUS BADGE FUNCTIONS
// ========================================

/**
 * Get a staff status badge.
 *
 * @param string $status Employment status
 * @return string HTML badge
 */
function getStaffStatusBadge($status)
{
    $badges = [
        'Active' => '<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">' . __('Active') . '</span>',
        'On Leave' => '<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-sm">' . __('On Leave') . '</span>',
        'Suspended' => '<span class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-sm">' . __('Suspended') . '</span>',
        'Probation' => '<span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm">' . __('Probation') . '</span>',
        'Terminated' => '<span class="bg-red-100 text-red-800 px-2 py-1 rounded text-sm">' . __('Terminated') . '</span>',
        'Resigned' => '<span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-sm">' . __('Resigned') . '</span>',
    ];

    return $badges[$status] ?? '<span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-sm">' . htmlspecialchars($status) . '</span>';
}

/**
 * Get a schedule status badge.
 *
 * @param string $status Schedule status
 * @return string HTML badge
 */
function getScheduleStatusBadge($status)
{
    $badges = [
        'Scheduled' => '<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-sm">' . __('Scheduled') . '</span>',
        'Confirmed' => '<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">' . __('Confirmed') . '</span>',
        'Completed' => '<span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-sm">' . __('Completed') . '</span>',
        'Cancelled' => '<span class="bg-red-100 text-red-800 px-2 py-1 rounded text-sm">' . __('Cancelled') . '</span>',
        'No Show' => '<span class="bg-red-200 text-red-900 px-2 py-1 rounded text-sm">' . __('No Show') . '</span>',
    ];

    return $badges[$status] ?? '<span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-sm">' . htmlspecialchars($status) . '</span>';
}

/**
 * Get a certification status badge.
 *
 * @param string $status Certification status
 * @return string HTML badge
 */
function getCertificationStatusBadge($status)
{
    $badges = [
        'Valid' => '<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">' . __('Valid') . '</span>',
        'Pending' => '<span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm">' . __('Pending') . '</span>',
        'Expired' => '<span class="bg-red-100 text-red-800 px-2 py-1 rounded text-sm">' . __('Expired') . '</span>',
        'Revoked' => '<span class="bg-red-200 text-red-900 px-2 py-1 rounded text-sm">' . __('Revoked') . '</span>',
    ];

    return $badges[$status] ?? '<span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-sm">' . htmlspecialchars($status) . '</span>';
}

/**
 * Get an overtime status badge.
 *
 * @param string $status Overtime status
 * @return string HTML badge
 */
function getOvertimeStatusBadge($status)
{
    $badges = [
        'Pending' => '<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-sm">' . __('Pending') . '</span>',
        'Approved' => '<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">' . __('Approved') . '</span>',
        'Denied' => '<span class="bg-red-100 text-red-800 px-2 py-1 rounded text-sm">' . __('Denied') . '</span>',
    ];

    return $badges[$status] ?? '<span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-sm">' . htmlspecialchars($status) . '</span>';
}

// ========================================
// QUALIFICATION HELPER FUNCTIONS
// ========================================

/**
 * Get the list of Quebec qualification levels.
 *
 * @return array Qualification level options
 */
function getQualificationLevels()
{
    return [
        'Unqualified' => __('Unqualified'),
        'In Training' => __('In Training'),
        'Partially Qualified' => __('Partially Qualified'),
        'Qualified' => __('Qualified'),
        'Senior Educator' => __('Senior Educator'),
        'Director' => __('Director'),
    ];
}

/**
 * Check if a qualification level counts as "qualified" for ratio purposes.
 *
 * @param string $level Qualification level
 * @return bool True if counts as qualified
 */
function isQualifiedLevel($level)
{
    $qualifiedLevels = ['Qualified', 'Senior Educator', 'Director'];
    return in_array($level, $qualifiedLevels);
}

/**
 * Get a qualification level badge.
 *
 * @param string $level Qualification level
 * @return string HTML badge
 */
function getQualificationBadge($level)
{
    $badges = [
        'Unqualified' => '<span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-sm">' . __('Unqualified') . '</span>',
        'In Training' => '<span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-sm">' . __('In Training') . '</span>',
        'Partially Qualified' => '<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-sm">' . __('Partially Qualified') . '</span>',
        'Qualified' => '<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-sm">' . __('Qualified') . '</span>',
        'Senior Educator' => '<span class="bg-green-200 text-green-900 px-2 py-1 rounded text-sm">' . __('Senior Educator') . '</span>',
        'Director' => '<span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-sm">' . __('Director') . '</span>',
    ];

    return $badges[$level] ?? '<span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-sm">' . htmlspecialchars($level) . '</span>';
}

// ========================================
// DATE/TIME HELPER FUNCTIONS
// ========================================

/**
 * Calculate days until a date.
 *
 * @param string $date Target date (Y-m-d format)
 * @return int Days until the date (negative if past)
 */
function daysUntilDate($date)
{
    $targetTime = strtotime($date);
    $now = strtotime(date('Y-m-d'));

    return (int) floor(($targetTime - $now) / (60 * 60 * 24));
}

/**
 * Get an urgency label based on days remaining.
 *
 * @param int $daysRemaining Days until expiry/deadline
 * @return string HTML urgency label
 */
function getUrgencyLabel($daysRemaining)
{
    if ($daysRemaining < 0) {
        return '<span class="bg-red-600 text-white px-2 py-1 rounded text-sm font-semibold">' . sprintf(__('%d days overdue'), abs($daysRemaining)) . '</span>';
    } elseif ($daysRemaining === 0) {
        return '<span class="bg-red-500 text-white px-2 py-1 rounded text-sm font-semibold">' . __('Due Today') . '</span>';
    } elseif ($daysRemaining <= 7) {
        return '<span class="bg-red-100 text-red-800 px-2 py-1 rounded text-sm font-semibold">' . sprintf(__('%d days'), $daysRemaining) . '</span>';
    } elseif ($daysRemaining <= 14) {
        return '<span class="bg-orange-100 text-orange-800 px-2 py-1 rounded text-sm">' . sprintf(__('%d days'), $daysRemaining) . '</span>';
    } elseif ($daysRemaining <= 30) {
        return '<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-sm">' . sprintf(__('%d days'), $daysRemaining) . '</span>';
    }

    return '<span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-sm">' . sprintf(__('%d days'), $daysRemaining) . '</span>';
}

/**
 * Get the start of the week (Monday) for a given date.
 *
 * @param string $date Date string
 * @return string Monday date (Y-m-d format)
 */
function getWeekStartDate($date)
{
    $timestamp = strtotime($date);
    $dayOfWeek = date('N', $timestamp); // 1 (Monday) to 7 (Sunday)
    $offset = $dayOfWeek - 1;
    return date('Y-m-d', strtotime("-{$offset} days", $timestamp));
}

/**
 * Get the end of the week (Sunday) for a given date.
 *
 * @param string $date Date string
 * @return string Sunday date (Y-m-d format)
 */
function getWeekEndDate($date)
{
    $timestamp = strtotime($date);
    $dayOfWeek = date('N', $timestamp); // 1 (Monday) to 7 (Sunday)
    $offset = 7 - $dayOfWeek;
    return date('Y-m-d', strtotime("+{$offset} days", $timestamp));
}

// ========================================
// SENSITIVE DATA MASKING
// ========================================

/**
 * Mask a SIN (Social Insurance Number) for display.
 *
 * @param string $sin Full SIN
 * @return string Masked SIN (e.g., "***-***-123")
 */
function maskSIN($sin)
{
    if (empty($sin)) {
        return '';
    }

    // Remove any formatting
    $sin = preg_replace('/[^0-9]/', '', $sin);

    if (strlen($sin) < 3) {
        return '***';
    }

    return '***-***-' . substr($sin, -3);
}

/**
 * Mask a bank account number for display.
 *
 * @param string $account Full account number
 * @return string Masked account (e.g., "****1234")
 */
function maskBankAccount($account)
{
    if (empty($account)) {
        return '';
    }

    // Remove any formatting
    $account = preg_replace('/[^0-9]/', '', $account);

    if (strlen($account) <= 4) {
        return '****';
    }

    return str_repeat('*', strlen($account) - 4) . substr($account, -4);
}

/**
 * Mask a transit number for display.
 *
 * @param string $transit Full transit number
 * @return string Masked transit (e.g., "***12")
 */
function maskTransitNumber($transit)
{
    if (empty($transit)) {
        return '';
    }

    // Remove any formatting
    $transit = preg_replace('/[^0-9]/', '', $transit);

    if (strlen($transit) <= 2) {
        return '***';
    }

    return str_repeat('*', strlen($transit) - 2) . substr($transit, -2);
}
