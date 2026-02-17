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
use Gibbon\Module\CareTracking\Domain\ChildDietaryGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Care Tracking'), 'careTracking.php');
$page->breadcrumbs->add(__('Weekly Menu'), 'menu_weekly.php');
$page->breadcrumbs->add(__('Edit Menu Slot'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/CareTracking/menu_weekly.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get parameters
    $date = $_GET['date'] ?? '';
    $mealType = $_GET['mealType'] ?? '';
    $weekStart = $_GET['weekStart'] ?? '';

    // Validate required parameters
    if (empty($date) || empty($mealType)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $page->addError(__('Invalid date format.'));
        return;
    }

    // Validate meal type
    $validMealTypes = ['Breakfast', 'Morning Snack', 'Lunch', 'Afternoon Snack', 'Dinner'];
    if (!in_array($mealType, $validMealTypes)) {
        $page->addError(__('Invalid meal type.'));
        return;
    }

    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateways via DI container
    $weeklyMenuGateway = $container->get(WeeklyMenuGateway::class);
    $menuItemGateway = $container->get(MenuItemGateway::class);
    $childDietaryGateway = $container->get(ChildDietaryGateway::class);

    // Meal types for display
    $mealTypeLabels = [
        'Breakfast'        => __('Breakfast'),
        'Morning Snack'    => __('Morning Snack'),
        'Lunch'            => __('Lunch'),
        'Afternoon Snack'  => __('Afternoon Snack'),
        'Dinner'           => __('Dinner'),
    ];

    // Calculate week start if not provided
    if (empty($weekStart)) {
        // Calculate Monday of the week containing the date
        $dateObj = new DateTime($date);
        $dayOfWeek = $dateObj->format('N'); // 1 = Monday, 7 = Sunday
        $daysToSubtract = $dayOfWeek - 1;
        $weekStart = $dateObj->modify("-{$daysToSubtract} days")->format('Y-m-d');
    }

    // Handle form actions
    $action = $_POST['action'] ?? '';

    // Handle adding menu items
    if ($action === 'addMenuItems') {
        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $session->get('csrf_token')) {
            $page->addError(__('Your request failed because you do not have access to this action.'));
        } else {
            $menuItemIDs = $_POST['menuItemIDs'] ?? [];
            $servingSize = trim($_POST['servingSize'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if (empty($menuItemIDs)) {
                $page->addError(__('Please select at least one menu item.'));
            } else {
                $successCount = 0;
                foreach ($menuItemIDs as $menuItemID) {
                    $result = $weeklyMenuGateway->setMenuForDateAndType(
                        $gibbonSchoolYearID,
                        $date,
                        $mealType,
                        $menuItemID,
                        $gibbonPersonID,
                        $servingSize ?: null,
                        $notes ?: null
                    );
                    if ($result !== false) {
                        $successCount++;
                    }
                }
                if ($successCount > 0) {
                    $page->addSuccess(__('Added {count} menu item(s) successfully.', ['count' => $successCount]));
                } else {
                    $page->addError(__('Failed to add menu items.'));
                }
            }
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

    // Handle clearing all items
    if ($action === 'clearAll') {
        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $session->get('csrf_token')) {
            $page->addError(__('Your request failed because you do not have access to this action.'));
        } else {
            $result = $weeklyMenuGateway->deleteMenuEntriesForDateAndType($date, $mealType, $gibbonSchoolYearID);
            if ($result) {
                $page->addSuccess(__('All menu items have been cleared from this slot.'));
            } else {
                $page->addError(__('Failed to clear menu items.'));
            }
        }
    }

    // Page header
    echo '<div class="flex justify-between items-center mb-4">';
    echo '<h2>' . __('Edit Menu Slot') . '</h2>';
    echo '</div>';

    // Display date and meal type info
    $dayOfWeek = date('l', strtotime($date));
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<div class="flex flex-wrap gap-6">';
    echo '<div>';
    echo '<span class="block text-sm text-gray-500">' . __('Day') . '</span>';
    echo '<span class="text-xl font-semibold">' . __($dayOfWeek) . ', ' . Format::date($date) . '</span>';
    echo '</div>';
    echo '<div>';
    echo '<span class="block text-sm text-gray-500">' . __('Meal Type') . '</span>';
    echo '<span class="text-xl font-semibold">' . ($mealTypeLabels[$mealType] ?? $mealType) . '</span>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Get current menu items for this date and meal type
    $currentMenuItems = $weeklyMenuGateway->selectMenuByDateAndType($date, $mealType, $gibbonSchoolYearID);

    // Display current menu items
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Current Menu Items') . '</h3>';

    if ($currentMenuItems->rowCount() > 0) {
        echo '<div class="space-y-2">';
        foreach ($currentMenuItems as $item) {
            echo '<div class="border rounded-lg p-3 flex justify-between items-start bg-gray-50">';
            echo '<div class="flex items-start gap-3">';

            // Item photo
            if (!empty($item['menuItemPhotoPath'])) {
                echo '<img src="' . $session->get('absoluteURL') . '/' . $item['menuItemPhotoPath'] . '" class="w-12 h-12 rounded object-cover" alt="">';
            } else {
                echo '<div class="w-12 h-12 rounded bg-gray-200 flex items-center justify-center text-gray-400">';
                echo '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
                echo '</div>';
            }

            echo '<div>';
            echo '<span class="font-medium block">' . htmlspecialchars($item['menuItemName']) . '</span>';
            echo '<span class="text-sm text-gray-500">' . htmlspecialchars($item['menuItemCategory'] ?? '') . '</span>';

            // Show allergens with warning styling
            if (!empty($item['allergenList'])) {
                echo '<div class="mt-1">';
                $allergens = explode(', ', $item['allergenList']);
                foreach ($allergens as $allergen) {
                    echo '<span class="inline-block bg-red-100 text-red-800 text-xs px-2 py-0.5 rounded mr-1 mb-1">' . htmlspecialchars(trim($allergen)) . '</span>';
                }
                echo '</div>';
            }

            // Show notes if any
            if (!empty($item['notes'])) {
                echo '<p class="text-sm text-gray-600 mt-1 italic">' . htmlspecialchars($item['notes']) . '</p>';
            }

            // Show serving size if any
            if (!empty($item['servingSize'])) {
                echo '<span class="text-xs text-gray-500 mt-1 block">' . __('Serving') . ': ' . htmlspecialchars($item['servingSize']) . '</span>';
            }

            echo '</div>';
            echo '</div>';

            // Remove button
            echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_weekly_edit.php&date=' . $date . '&mealType=' . urlencode($mealType) . '&weekStart=' . $weekStart . '" class="inline">';
            echo '<input type="hidden" name="action" value="removeMenuItem">';
            echo '<input type="hidden" name="gibbonCareWeeklyMenuID" value="' . $item['gibbonCareWeeklyMenuID'] . '">';
            echo '<button type="submit" class="text-red-500 hover:text-red-700 p-2" title="' . __('Remove') . '" onclick="return confirm(\'' . __('Are you sure you want to remove this item?') . '\');">';
            echo '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>';
            echo '</button>';
            echo '</form>';
            echo '</div>';
        }
        echo '</div>';

        // Clear all button
        echo '<div class="mt-4">';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_weekly_edit.php&date=' . $date . '&mealType=' . urlencode($mealType) . '&weekStart=' . $weekStart . '" class="inline">';
        echo '<input type="hidden" name="action" value="clearAll">';
        echo '<input type="hidden" name="csrf_token" value="' . $session->get('csrf_token') . '">';
        echo '<button type="submit" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 text-sm" onclick="return confirm(\'' . __('Are you sure you want to remove all menu items from this slot?') . '\');">' . __('Clear All Items') . '</button>';
        echo '</form>';
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 italic">' . __('No menu items assigned to this slot yet.') . '</p>';
    }
    echo '</div>';

    // Check for allergen warnings (children who have allergies matching menu items)
    $allergenWarnings = $weeklyMenuGateway->selectAllergenWarningsForDate($date, $gibbonSchoolYearID);
    $relevantWarnings = [];

    // Filter warnings to only this meal type
    foreach ($allergenWarnings as $warning) {
        if ($warning['mealType'] === $mealType) {
            $childKey = $warning['gibbonPersonID'];
            if (!isset($relevantWarnings[$childKey])) {
                $relevantWarnings[$childKey] = [
                    'name' => Format::name('', $warning['preferredName'], $warning['surname'], 'Student', false, true),
                    'allergens' => [],
                ];
            }
            $relevantWarnings[$childKey]['allergens'][] = [
                'allergen' => $warning['allergen'],
                'menuItem' => $warning['menuItemName'],
                'severity' => $warning['severity'],
            ];
        }
    }

    if (!empty($relevantWarnings)) {
        echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">';
        echo '<h3 class="text-lg font-semibold text-red-700 mb-3">';
        echo '<svg class="w-5 h-5 inline-block mr-2" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>';
        echo __('Allergen Warnings');
        echo '</h3>';
        echo '<p class="text-sm text-red-600 mb-3">' . __('The following children have allergies that conflict with items in this menu slot:') . '</p>';
        echo '<div class="space-y-2">';

        foreach ($relevantWarnings as $warning) {
            echo '<div class="bg-white rounded p-2 border border-red-100">';
            echo '<span class="font-medium">' . htmlspecialchars($warning['name']) . '</span>';
            echo '<ul class="list-disc list-inside text-sm text-red-700 mt-1">';
            foreach ($warning['allergens'] as $allergenInfo) {
                $severityClass = '';
                if ($allergenInfo['severity'] === 'Severe') {
                    $severityClass = 'font-bold';
                }
                echo '<li class="' . $severityClass . '">';
                echo htmlspecialchars($allergenInfo['allergen']);
                echo ' (' . htmlspecialchars($allergenInfo['severity']) . ')';
                echo ' - ' . __('in') . ' ' . htmlspecialchars($allergenInfo['menuItem']);
                echo '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    // Add menu items section
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Add Menu Items') . '</h3>';

    // Get available menu items grouped by category
    $menuItemsByCategory = $menuItemGateway->selectActiveMenuItemsByCategory();

    // Get all menu items with allergens for reference
    $menuItemsWithAllergens = $menuItemGateway->selectMenuItemsWithAllergens(true);
    $allergensByItem = [];
    foreach ($menuItemsWithAllergens as $item) {
        $allergensByItem[$item['gibbonCareMenuItemID']] = $item['allergenList'] ?? '';
    }

    // Get IDs of already assigned items
    $assignedItemIDs = [];
    $currentMenuItems = $weeklyMenuGateway->selectMenuByDateAndType($date, $mealType, $gibbonSchoolYearID);
    foreach ($currentMenuItems as $item) {
        $assignedItemIDs[] = $item['gibbonCareMenuItemID'];
    }

    echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_weekly_edit.php&date=' . $date . '&mealType=' . urlencode($mealType) . '&weekStart=' . $weekStart . '">';
    echo '<input type="hidden" name="action" value="addMenuItems">';
    echo '<input type="hidden" name="csrf_token" value="' . $session->get('csrf_token') . '">';

    // Category filter (client-side)
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium text-gray-700 mb-1">' . __('Filter by Category') . '</label>';
    echo '<select id="categoryFilter" class="border rounded px-3 py-2" onchange="filterByCategory(this.value)">';
    echo '<option value="all">' . __('All Categories') . '</option>';
    foreach (array_keys($menuItemsByCategory) as $category) {
        echo '<option value="' . htmlspecialchars($category) . '">' . __($category) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // Menu items grid
    if (!empty($menuItemsByCategory)) {
        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3" id="menuItemsGrid">';

        foreach ($menuItemsByCategory as $category => $items) {
            foreach ($items as $item) {
                $itemID = $item['gibbonCareMenuItemID'];
                $isAssigned = in_array($itemID, $assignedItemIDs);
                $allergens = $allergensByItem[$itemID] ?? '';
                $hasAllergens = !empty($allergens);

                $cardClasses = 'border rounded-lg p-3 menu-item-card';
                $cardClasses .= $isAssigned ? ' bg-green-50 border-green-200' : ' bg-white hover:bg-gray-50';

                echo '<label class="' . $cardClasses . ' cursor-pointer block" data-category="' . htmlspecialchars($category) . '">';
                echo '<div class="flex items-start gap-3">';

                // Checkbox
                $disabled = $isAssigned ? ' disabled' : '';
                $checked = $isAssigned ? ' checked' : '';
                echo '<input type="checkbox" name="menuItemIDs[]" value="' . $itemID . '" class="mt-1 menuItemCheckbox"' . $disabled . $checked . '>';

                // Photo
                if (!empty($item['photoPath'])) {
                    echo '<img src="' . $session->get('absoluteURL') . '/' . $item['photoPath'] . '" class="w-10 h-10 rounded object-cover flex-shrink-0" alt="">';
                } else {
                    echo '<div class="w-10 h-10 rounded bg-gray-200 flex-shrink-0 flex items-center justify-center text-gray-400">';
                    echo '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
                    echo '</div>';
                }

                // Info
                echo '<div class="flex-1 min-w-0">';
                echo '<span class="font-medium block truncate">' . htmlspecialchars($item['name']) . '</span>';
                echo '<span class="text-xs text-gray-500">' . __($category) . '</span>';

                // Allergens
                if ($hasAllergens) {
                    echo '<div class="mt-1">';
                    $allergenList = explode(', ', $allergens);
                    foreach ($allergenList as $allergen) {
                        echo '<span class="inline-block bg-red-100 text-red-700 text-xs px-1.5 py-0.5 rounded mr-1">' . htmlspecialchars(trim($allergen)) . '</span>';
                    }
                    echo '</div>';
                }

                // Already assigned badge
                if ($isAssigned) {
                    echo '<span class="inline-block bg-green-100 text-green-700 text-xs px-2 py-0.5 rounded mt-1">' . __('Already assigned') . '</span>';
                }

                echo '</div>';
                echo '</div>';
                echo '</label>';
            }
        }

        echo '</div>';
    } else {
        echo '<p class="text-gray-500 italic">' . __('No menu items available. Please add menu items first.') . '</p>';
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_items_add.php" class="text-blue-600 hover:underline">' . __('Add Menu Items') . '</a>';
    }

    // Optional serving size and notes
    echo '<div class="mt-4 border-t pt-4">';
    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';

    echo '<div>';
    echo '<label class="block text-sm font-medium text-gray-700 mb-1">' . __('Serving Size (optional)') . '</label>';
    echo '<input type="text" name="servingSize" class="border rounded px-3 py-2 w-full" placeholder="' . __('e.g., 1 cup, 100g') . '">';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium text-gray-700 mb-1">' . __('Notes (optional)') . '</label>';
    echo '<input type="text" name="notes" class="border rounded px-3 py-2 w-full" placeholder="' . __('Any special notes...') . '">';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Submit button
    echo '<div class="mt-4">';
    echo '<button type="submit" class="bg-green-500 text-white px-6 py-2 rounded hover:bg-green-600">' . __('Add Selected Items') . '</button>';
    echo '</div>';

    echo '</form>';
    echo '</div>';

    // Quick Add Suggestion
    echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">';
    echo '<h4 class="font-semibold text-blue-800 mb-2">' . __('Quick Tips') . '</h4>';
    echo '<ul class="text-sm text-blue-700 list-disc list-inside space-y-1">';
    echo '<li>' . __('Select multiple menu items at once by checking their boxes') . '</li>';
    echo '<li>' . __('Items with allergens are marked with red badges') . '</li>';
    echo '<li>' . __('Already assigned items are highlighted in green and cannot be added again') . '</li>';
    echo '<li>' . __('Serving size and notes apply to all items added in one submission') . '</li>';
    echo '</ul>';
    echo '</div>';

    // JavaScript for category filtering
    echo '<script>
    function filterByCategory(category) {
        var cards = document.querySelectorAll(".menu-item-card");
        cards.forEach(function(card) {
            if (category === "all" || card.getAttribute("data-category") === category) {
                card.style.display = "";
            } else {
                card.style.display = "none";
            }
        });
    }
    </script>';

    // Navigation links
    echo '<div class="flex justify-between items-center mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_weekly.php&weekStart=' . $weekStart . '" class="text-blue-600 hover:underline">&larr; ' . __('Back to Weekly Menu') . '</a>';

    // Previous/Next day quick navigation
    $prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
    $nextDate = date('Y-m-d', strtotime($date . ' +1 day'));

    echo '<div class="flex gap-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_weekly_edit.php&date=' . $prevDate . '&mealType=' . urlencode($mealType) . '&weekStart=' . $weekStart . '" class="text-gray-600 hover:text-blue-600">&larr; ' . __('Previous Day') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_weekly_edit.php&date=' . $nextDate . '&mealType=' . urlencode($mealType) . '&weekStart=' . $weekStart . '" class="text-gray-600 hover:text-blue-600">' . __('Next Day') . ' &rarr;</a>';
    echo '</div>';
    echo '</div>';
}
