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
use Gibbon\Services\Format;
use Gibbon\Module\CareTracking\Domain\WeeklyMenuGateway;
use Gibbon\Module\CareTracking\Domain\MenuItemGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Care Tracking'), 'careTracking.php');
$page->breadcrumbs->add(__('Weekly Menu'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/CareTracking/menu_weekly.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateways via DI container
    $weeklyMenuGateway = $container->get(WeeklyMenuGateway::class);
    $menuItemGateway = $container->get(MenuItemGateway::class);

    // Meal types
    $mealTypes = [
        'Breakfast'        => __('Breakfast'),
        'Morning Snack'    => __('Morning Snack'),
        'Lunch'            => __('Lunch'),
        'Afternoon Snack'  => __('Afternoon Snack'),
        'Dinner'           => __('Dinner'),
    ];

    // Days of the week
    $daysOfWeek = [
        0 => __('Monday'),
        1 => __('Tuesday'),
        2 => __('Wednesday'),
        3 => __('Thursday'),
        4 => __('Friday'),
    ];

    // Get week start date from request or default to current week's Monday
    $weekStartDate = $_GET['weekStart'] ?? null;
    if (empty($weekStartDate) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStartDate)) {
        // Calculate Monday of current week
        $weekStartDate = date('Y-m-d', strtotime('monday this week'));
    }

    // Calculate week end date (Friday)
    $weekEndDate = date('Y-m-d', strtotime($weekStartDate . ' +4 days'));

    // Calculate previous and next week dates
    $prevWeekStart = date('Y-m-d', strtotime($weekStartDate . ' -7 days'));
    $nextWeekStart = date('Y-m-d', strtotime($weekStartDate . ' +7 days'));

    // Handle actions
    $action = $_POST['action'] ?? '';

    // Handle adding a menu item to a day/meal
    if ($action === 'addMenuItem') {
        $date = $_POST['date'] ?? '';
        $mealType = $_POST['mealType'] ?? '';
        $menuItemID = $_POST['gibbonCareMenuItemID'] ?? '';
        $servingSize = $_POST['servingSize'] ?? null;
        $notes = $_POST['notes'] ?? null;

        if (!empty($date) && !empty($mealType) && !empty($menuItemID)) {
            $result = $weeklyMenuGateway->setMenuForDateAndType(
                $gibbonSchoolYearID,
                $date,
                $mealType,
                $menuItemID,
                $gibbonPersonID,
                $servingSize,
                $notes
            );

            if ($result !== false) {
                $page->addSuccess(__('Menu item has been added successfully.'));
            } else {
                $page->addError(__('Failed to add menu item.'));
            }
        } else {
            $page->addError(__('Please select a date, meal type, and menu item.'));
        }
    }

    // Handle removing a menu item
    if ($action === 'removeMenuItem') {
        $menuEntryID = $_POST['gibbonCareWeeklyMenuID'] ?? '';

        if (!empty($menuEntryID)) {
            $result = $weeklyMenuGateway->deleteMenuEntry($menuEntryID);

            if ($result) {
                $page->addSuccess(__('Menu item has been removed.'));
            } else {
                $page->addError(__('Failed to remove menu item.'));
            }
        }
    }

    // Handle copying week
    if ($action === 'copyWeek') {
        $sourceWeekStart = $_POST['sourceWeekStart'] ?? '';
        $targetWeekStart = $_POST['targetWeekStart'] ?? '';

        if (!empty($sourceWeekStart) && !empty($targetWeekStart)) {
            $copiedCount = $weeklyMenuGateway->copyWeekMenu(
                $sourceWeekStart,
                $targetWeekStart,
                $gibbonSchoolYearID,
                $gibbonPersonID
            );

            if ($copiedCount > 0) {
                $page->addSuccess(__('Copied {count} menu items to the target week.', ['count' => $copiedCount]));
            } else {
                $page->addWarning(__('No menu items were copied. The source week may be empty.'));
            }
        }
    }

    // Page header
    echo '<div class="flex justify-between items-center mb-4">';
    echo '<h2>' . __('Weekly Menu Planning') . '</h2>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_items.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">' . __('Manage Menu Items') . '</a>';
    echo '</div>';

    // Week navigation
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<div class="flex justify-between items-center">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_weekly.php&weekStart=' . $prevWeekStart . '" class="text-blue-600 hover:underline">&larr; ' . __('Previous Week') . '</a>';
    echo '<div class="text-center">';
    echo '<span class="text-lg font-semibold">' . Format::date($weekStartDate) . ' - ' . Format::date($weekEndDate) . '</span>';
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_weekly.php&weekStart=' . $nextWeekStart . '" class="text-blue-600 hover:underline">' . __('Next Week') . ' &rarr;</a>';
    echo '</div>';

    // Jump to date form
    echo '<div class="mt-3 flex justify-center">';
    echo '<form method="get" action="' . $session->get('absoluteURL') . '/index.php" class="inline-flex items-center gap-2">';
    echo '<input type="hidden" name="q" value="/modules/CareTracking/menu_weekly.php">';
    echo '<label class="text-sm">' . __('Jump to week of') . ':</label>';
    echo '<input type="date" name="weekStart" value="' . $weekStartDate . '" class="border rounded px-2 py-1">';
    echo '<button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600 text-sm">' . __('Go') . '</button>';
    echo '</form>';

    // Quick link to current week
    $currentWeekStart = date('Y-m-d', strtotime('monday this week'));
    if ($weekStartDate !== $currentWeekStart) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_weekly.php&weekStart=' . $currentWeekStart . '" class="ml-3 text-blue-600 hover:underline text-sm">' . __('Current Week') . '</a>';
    }
    echo '</div>';
    echo '</div>';

    // Get weekly menu data
    $weeklyMenu = $weeklyMenuGateway->getMenuForWeekStructured($weekStartDate, $gibbonSchoolYearID);

    // Get available menu items for dropdown
    $menuItemOptions = $menuItemGateway->selectActiveMenuItemsAsOptions();

    // Get summary for this week
    $weekSummary = $weeklyMenuGateway->getMenuSummaryByDateRange($weekStartDate, $weekEndDate, $gibbonSchoolYearID);

    // Week Summary Stats
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Week Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-7 gap-2 text-center text-sm">';

    echo '<div class="bg-gray-50 rounded p-2">';
    echo '<span class="block text-xl font-bold">' . ($weekSummary['totalMenuItems'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Total Items') . '</span>';
    echo '</div>';

    echo '<div class="bg-yellow-50 rounded p-2">';
    echo '<span class="block text-xl font-bold text-yellow-600">' . ($weekSummary['breakfastCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Breakfast') . '</span>';
    echo '</div>';

    echo '<div class="bg-orange-50 rounded p-2">';
    echo '<span class="block text-xl font-bold text-orange-600">' . ($weekSummary['morningSnackCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('AM Snack') . '</span>';
    echo '</div>';

    echo '<div class="bg-green-50 rounded p-2">';
    echo '<span class="block text-xl font-bold text-green-600">' . ($weekSummary['lunchCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Lunch') . '</span>';
    echo '</div>';

    echo '<div class="bg-blue-50 rounded p-2">';
    echo '<span class="block text-xl font-bold text-blue-600">' . ($weekSummary['afternoonSnackCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('PM Snack') . '</span>';
    echo '</div>';

    echo '<div class="bg-purple-50 rounded p-2">';
    echo '<span class="block text-xl font-bold text-purple-600">' . ($weekSummary['dinnerCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Dinner') . '</span>';
    echo '</div>';

    echo '<div class="bg-indigo-50 rounded p-2">';
    echo '<span class="block text-xl font-bold text-indigo-600">' . ($weekSummary['daysScheduled'] ?? 0) . '/5</span>';
    echo '<span class="text-xs text-gray-500">' . __('Days Planned') . '</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Weekly Calendar View
    echo '<div class="bg-white rounded-lg shadow overflow-hidden mb-4">';
    echo '<div class="overflow-x-auto">';
    echo '<table class="w-full border-collapse">';

    // Header row with days
    echo '<thead>';
    echo '<tr class="bg-gray-100">';
    echo '<th class="border p-2 text-left w-32">' . __('Meal') . '</th>';
    for ($i = 0; $i < 5; $i++) {
        $currentDate = date('Y-m-d', strtotime($weekStartDate . ' +' . $i . ' days'));
        $dayName = $daysOfWeek[$i];
        $formattedDate = Format::date($currentDate);
        $isToday = $currentDate === date('Y-m-d');
        $todayClass = $isToday ? 'bg-blue-100' : '';
        echo '<th class="border p-2 text-center ' . $todayClass . '">';
        echo '<span class="block font-semibold">' . $dayName . '</span>';
        echo '<span class="text-xs text-gray-500">' . $formattedDate . '</span>';
        if ($isToday) {
            echo '<span class="block text-xs text-blue-600 font-medium">' . __('Today') . '</span>';
        }
        echo '</th>';
    }
    echo '</tr>';
    echo '</thead>';

    // Body rows for each meal type
    echo '<tbody>';
    foreach ($mealTypes as $mealKey => $mealLabel) {
        $mealColors = [
            'Breakfast'        => 'bg-yellow-50',
            'Morning Snack'    => 'bg-orange-50',
            'Lunch'            => 'bg-green-50',
            'Afternoon Snack'  => 'bg-blue-50',
            'Dinner'           => 'bg-purple-50',
        ];
        $rowColor = $mealColors[$mealKey] ?? '';

        echo '<tr class="' . $rowColor . '">';
        echo '<td class="border p-2 font-medium text-sm">' . $mealLabel . '</td>';

        for ($i = 0; $i < 5; $i++) {
            $currentDate = date('Y-m-d', strtotime($weekStartDate . ' +' . $i . ' days'));
            $isToday = $currentDate === date('Y-m-d');
            $cellClass = $isToday ? 'bg-blue-50' : '';

            echo '<td class="border p-2 align-top ' . $cellClass . '" style="min-width: 150px;">';

            // Get menu items for this date and meal type
            $menuItems = $weeklyMenu[$currentDate][$mealKey] ?? [];

            // Display existing menu items
            if (!empty($menuItems)) {
                echo '<div class="space-y-1 mb-2">';
                foreach ($menuItems as $item) {
                    echo '<div class="bg-white rounded border p-1.5 text-xs shadow-sm flex justify-between items-start">';
                    echo '<div>';
                    echo '<span class="font-medium block">' . htmlspecialchars($item['menuItemName']) . '</span>';
                    if (!empty($item['allergenList'])) {
                        echo '<span class="text-red-500 text-xs" title="' . __('Contains allergens') . '">⚠ ' . htmlspecialchars($item['allergenList']) . '</span>';
                    }
                    if (!empty($item['notes'])) {
                        echo '<span class="text-gray-500 block">' . htmlspecialchars($item['notes']) . '</span>';
                    }
                    echo '</div>';
                    echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_weekly.php&weekStart=' . $weekStartDate . '" class="inline">';
                    echo '<input type="hidden" name="action" value="removeMenuItem">';
                    echo '<input type="hidden" name="gibbonCareWeeklyMenuID" value="' . $item['gibbonCareWeeklyMenuID'] . '">';
                    echo '<button type="submit" class="text-red-500 hover:text-red-700 ml-1" title="' . __('Remove') . '" onclick="return confirm(\'' . __('Are you sure you want to remove this item?') . '\');">&times;</button>';
                    echo '</form>';
                    echo '</div>';
                }
                echo '</div>';
            }

            // Add menu item form (compact)
            echo '<div class="add-item-form" data-date="' . $currentDate . '" data-meal="' . htmlspecialchars($mealKey) . '">';
            echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_weekly.php&weekStart=' . $weekStartDate . '">';
            echo '<input type="hidden" name="action" value="addMenuItem">';
            echo '<input type="hidden" name="date" value="' . $currentDate . '">';
            echo '<input type="hidden" name="mealType" value="' . htmlspecialchars($mealKey) . '">';
            echo '<select name="gibbonCareMenuItemID" class="w-full border rounded px-1 py-0.5 text-xs mb-1">';
            echo '<option value="">' . __('+ Add item...') . '</option>';
            foreach ($menuItemOptions as $itemID => $itemName) {
                echo '<option value="' . $itemID . '">' . htmlspecialchars($itemName) . '</option>';
            }
            echo '</select>';
            echo '<input type="text" name="notes" placeholder="' . __('Notes (optional)') . '" class="w-full border rounded px-1 py-0.5 text-xs mb-1">';
            echo '<button type="submit" class="bg-green-500 text-white text-xs px-2 py-0.5 rounded hover:bg-green-600 w-full">' . __('Add') . '</button>';
            echo '</form>';
            echo '</div>';

            echo '</td>';
        }

        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '</div>';

    // Copy Week Section
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Copy Menu from Another Week') . '</h3>';
    echo '<p class="text-sm text-gray-600 mb-3">' . __('Copy all menu items from a previous week to another week.') . '</p>';
    echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_weekly.php&weekStart=' . $weekStartDate . '" class="flex flex-wrap gap-4 items-end">';
    echo '<input type="hidden" name="action" value="copyWeek">';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Source Week (Monday)') . '</label>';
    echo '<input type="date" name="sourceWeekStart" class="border rounded px-3 py-2" required>';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Target Week (Monday)') . '</label>';
    echo '<input type="date" name="targetWeekStart" value="' . $weekStartDate . '" class="border rounded px-3 py-2" required>';
    echo '</div>';

    echo '<div>';
    echo '<button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600" onclick="return confirm(\'' . __('This will add menu items to the target week. Existing items will not be removed. Continue?') . '\');">' . __('Copy Week') . '</button>';
    echo '</div>';

    echo '</form>';
    echo '</div>';

    // Allergen Warnings Section (if there are any for today)
    $today = date('Y-m-d');
    if ($today >= $weekStartDate && $today <= $weekEndDate) {
        $allergenWarnings = $weeklyMenuGateway->selectAllergenWarningsForDate($today, $gibbonSchoolYearID);
        if ($allergenWarnings->rowCount() > 0) {
            echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">';
            echo '<h3 class="text-lg font-semibold text-red-700 mb-3">⚠ ' . __("Today's Allergen Warnings") . '</h3>';
            echo '<p class="text-sm text-red-600 mb-3">' . __('The following children have allergies that conflict with today\'s menu:') . '</p>';
            echo '<div class="space-y-2">';

            $warnings = [];
            foreach ($allergenWarnings as $warning) {
                $childName = Format::name('', $warning['preferredName'], $warning['surname'], 'Student', false, true);
                $key = $warning['gibbonPersonID'];
                if (!isset($warnings[$key])) {
                    $warnings[$key] = [
                        'name' => $childName,
                        'allergens' => [],
                    ];
                }
                $warnings[$key]['allergens'][] = [
                    'allergen' => $warning['allergen'],
                    'menuItem' => $warning['menuItemName'],
                    'mealType' => $warning['mealType'],
                ];
            }

            foreach ($warnings as $childWarning) {
                echo '<div class="bg-white rounded p-2 border border-red-100">';
                echo '<span class="font-medium">' . htmlspecialchars($childWarning['name']) . '</span>';
                echo '<ul class="list-disc list-inside text-sm text-red-700 mt-1">';
                foreach ($childWarning['allergens'] as $allergenInfo) {
                    echo '<li>' . htmlspecialchars($allergenInfo['allergen']) . ' in ' . htmlspecialchars($allergenInfo['menuItem']) . ' (' . __($allergenInfo['mealType']) . ')</li>';
                }
                echo '</ul>';
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';
        }
    }

    // Print/Export Options
    echo '<div class="flex gap-2 mb-4">';
    echo '<button onclick="window.print();" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">' . __('Print Menu') . '</button>';
    echo '</div>';

    // Link back to Care Tracking dashboard
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Care Tracking') . '</a>';
    echo '</div>';
}
