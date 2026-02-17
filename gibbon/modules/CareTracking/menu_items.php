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
use Gibbon\Module\CareTracking\Domain\MenuItemGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Care Tracking'), 'careTracking.php');
$page->breadcrumbs->add(__('Menu Items'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/CareTracking/menu_items.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get gateway via DI container
    $menuItemGateway = $container->get(MenuItemGateway::class);

    // Get filter parameters
    $category = $_GET['category'] ?? '';
    $showInactive = $_GET['showInactive'] ?? 'N';

    // Category options
    $categoryOptions = [
        ''             => __('All Categories'),
        'Main Course'  => __('Main Course'),
        'Side Dish'    => __('Side Dish'),
        'Snack'        => __('Snack'),
        'Beverage'     => __('Beverage'),
        'Dessert'      => __('Dessert'),
        'Fruit'        => __('Fruit'),
        'Vegetable'    => __('Vegetable'),
        'Dairy'        => __('Dairy'),
        'Protein'      => __('Protein'),
        'Grain'        => __('Grain'),
    ];

    // Page header with Add button
    echo '<div class="flex justify-between items-center mb-4">';
    echo '<h2>' . __('Menu Items') . '</h2>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_items_add.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Add Menu Item') . '</a>';
    echo '</div>';

    // Filter form
    $form = Form::create('menuItemsFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/CareTracking/menu_items.php');

    $row = $form->addRow();
    $row->addLabel('category', __('Category'));
    $row->addSelect('category')
        ->fromArray($categoryOptions)
        ->selected($category);

    $row = $form->addRow();
    $row->addLabel('showInactive', __('Show Inactive'));
    $row->addCheckbox('showInactive')
        ->setValue('Y')
        ->checked($showInactive === 'Y');

    $row = $form->addRow();
    $row->addSearchSubmit($gibbon->session, __('Clear Filters'));

    echo $form->getOutput();

    // Build query criteria
    $criteria = $menuItemGateway->newQueryCriteria()
        ->sortBy(['name'])
        ->fromPOST();

    // Apply filters
    if (!empty($category)) {
        $criteria->filterBy('category', $category);
    }
    if ($showInactive !== 'Y') {
        $criteria->filterBy('isActive', 'Y');
    }

    // Get menu items
    $menuItems = $menuItemGateway->queryMenuItems($criteria);

    // Get allergens for each menu item
    $allergensByItem = [];
    foreach ($menuItems as $item) {
        $allergens = $menuItemGateway->selectAllergensByMenuItem($item['gibbonCareMenuItemID']);
        $allergensByItem[$item['gibbonCareMenuItemID']] = $allergens->fetchAll();
    }

    // Build DataTable
    $table = DataTable::createPaginated('menuItems', $criteria);
    $table->setTitle(__('Menu Items'));

    // Add columns
    $table->addColumn('name', __('Name'))
        ->sortable()
        ->format(function ($row) use ($session) {
            $output = '<div class="flex items-center">';
            if (!empty($row['photoPath'])) {
                $output .= '<img src="' . $session->get('absoluteURL') . '/' . $row['photoPath'] . '" class="w-10 h-10 rounded object-cover mr-3" alt="">';
            } else {
                $output .= '<div class="w-10 h-10 rounded bg-gray-200 mr-3 flex items-center justify-center text-gray-500 text-xs">' . __('No image') . '</div>';
            }
            $output .= '<div>';
            $output .= '<span class="font-medium">' . htmlspecialchars($row['name']) . '</span>';
            if (!empty($row['description'])) {
                $output .= '<span class="block text-xs text-gray-500">' . htmlspecialchars(substr($row['description'], 0, 50)) . (strlen($row['description']) > 50 ? '...' : '') . '</span>';
            }
            $output .= '</div>';
            $output .= '</div>';
            return $output;
        });

    $table->addColumn('category', __('Category'))
        ->sortable()
        ->format(function ($row) {
            $categoryColors = [
                'Main Course' => 'blue',
                'Side Dish'   => 'green',
                'Snack'       => 'yellow',
                'Beverage'    => 'purple',
                'Dessert'     => 'pink',
                'Fruit'       => 'orange',
                'Vegetable'   => 'green',
                'Dairy'       => 'indigo',
                'Protein'     => 'red',
                'Grain'       => 'amber',
            ];
            $color = $categoryColors[$row['category']] ?? 'gray';
            return '<span class="bg-' . $color . '-100 text-' . $color . '-800 text-xs px-2 py-1 rounded">' . __($row['category']) . '</span>';
        });

    $table->addColumn('allergens', __('Allergens'))
        ->notSortable()
        ->format(function ($row) use ($allergensByItem) {
            $allergens = $allergensByItem[$row['gibbonCareMenuItemID']] ?? [];
            if (empty($allergens)) {
                return '<span class="text-gray-400 text-xs">' . __('None') . '</span>';
            }
            $badges = [];
            foreach ($allergens as $allergen) {
                $severityColors = [
                    'Severe'   => 'red',
                    'Moderate' => 'orange',
                    'Mild'     => 'yellow',
                ];
                $color = $severityColors[$allergen['severity']] ?? 'gray';
                $badges[] = '<span class="bg-' . $color . '-100 text-' . $color . '-800 text-xs px-1.5 py-0.5 rounded" title="' . htmlspecialchars($allergen['severity']) . '">' . htmlspecialchars($allergen['allergen']) . '</span>';
            }
            return '<div class="flex flex-wrap gap-1">' . implode('', $badges) . '</div>';
        });

    $table->addColumn('nutrition', __('Nutrition'))
        ->notSortable()
        ->format(function ($row) {
            if (empty($row['calories'])) {
                return '<span class="text-gray-400 text-xs">' . __('Not set') . '</span>';
            }
            $output = '<div class="text-xs">';
            $output .= '<span class="font-medium">' . $row['calories'] . '</span> ' . __('cal');
            if (!empty($row['protein'])) {
                $output .= ' | <span class="text-gray-600">P:</span> ' . $row['protein'] . 'g';
            }
            if (!empty($row['carbohydrates'])) {
                $output .= ' | <span class="text-gray-600">C:</span> ' . $row['carbohydrates'] . 'g';
            }
            if (!empty($row['fat'])) {
                $output .= ' | <span class="text-gray-600">F:</span> ' . $row['fat'] . 'g';
            }
            $output .= '</div>';
            return $output;
        });

    $table->addColumn('isActive', __('Status'))
        ->sortable()
        ->format(function ($row) {
            if ($row['isActive'] === 'Y') {
                return '<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">' . __('Active') . '</span>';
            }
            return '<span class="bg-gray-100 text-gray-600 text-xs px-2 py-1 rounded">' . __('Inactive') . '</span>';
        });

    // Add action column
    $table->addActionColumn()
        ->addParam('gibbonCareMenuItemID')
        ->format(function ($row, $actions) {
            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/CareTracking/menu_items_edit.php');

            if ($row['isActive'] === 'Y') {
                $actions->addAction('delete', __('Deactivate'))
                    ->setIcon('iconCross')
                    ->setURL('/modules/CareTracking/menu_items_delete.php')
                    ->addConfirmation(__('Are you sure you want to deactivate this menu item?'));
            }
        });

    // Output table
    if ($menuItems->count() > 0) {
        echo $table->render($menuItems);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        if (!empty($category)) {
            echo __('No menu items found in this category.');
        } else {
            echo __('No menu items found. Click "Add Menu Item" to create your first menu item.');
        }
        echo '</div>';
    }

    // Summary stats
    $allMenuItems = $menuItemGateway->selectMenuItemsWithAllergens(false);
    $activeCount = 0;
    $withAllergens = 0;
    foreach ($allMenuItems as $item) {
        if ($item['isActive'] === 'Y') {
            $activeCount++;
        }
        if (!empty($item['allergenList'])) {
            $withAllergens++;
        }
    }

    echo '<div class="bg-white rounded-lg shadow p-4 mt-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Menu Item Statistics') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">';

    echo '<div class="bg-gray-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold">' . $allMenuItems->rowCount() . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Total Items') . '</span>';
    echo '</div>';

    echo '<div class="bg-green-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold text-green-600">' . $activeCount . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Active Items') . '</span>';
    echo '</div>';

    echo '<div class="bg-orange-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold text-orange-600">' . $withAllergens . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('With Allergens') . '</span>';
    echo '</div>';

    $categories = $menuItemGateway->getActiveCategories();
    echo '<div class="bg-blue-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold text-blue-600">' . count($categories) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Categories') . '</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Link back to Care Tracking dashboard
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Care Tracking') . '</a>';
    echo '</div>';
}
