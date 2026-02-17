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
use Gibbon\Module\CareTracking\Domain\WeeklyMenuGateway;
use Gibbon\Module\CareTracking\Domain\MenuItemGateway;
use Gibbon\Module\CareTracking\Domain\ChildDietaryGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Care Tracking'), 'careTracking.php');
$page->breadcrumbs->add(__('Menu Reports'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/CareTracking/menu_reports.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get gateways via DI container
    $weeklyMenuGateway = $container->get(WeeklyMenuGateway::class);
    $menuItemGateway = $container->get(MenuItemGateway::class);
    $childDietaryGateway = $container->get(ChildDietaryGateway::class);

    // Get report type and date range from request
    $reportType = $_GET['reportType'] ?? 'nutritional';
    $dateFrom = $_GET['dateFrom'] ?? date('Y-m-d', strtotime('monday this week'));
    $dateTo = $_GET['dateTo'] ?? date('Y-m-d', strtotime('friday this week'));

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $dateFrom = date('Y-m-d', strtotime('monday this week'));
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $dateTo = date('Y-m-d', strtotime('friday this week'));
    }

    // Page header
    echo '<h2>' . __('Menu Reports') . '</h2>';

    // Report filter form
    $form = Form::create('reportFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/CareTracking/menu_reports.php');

    $row = $form->addRow();
    $row->addLabel('reportType', __('Report Type'));
    $row->addSelect('reportType')
        ->fromArray([
            'nutritional' => __('Nutritional Summary'),
            'allergen' => __('Allergen Exposure'),
            'dietary' => __('Dietary Overview'),
        ])
        ->selected($reportType)
        ->required();

    $row = $form->addRow();
    $row->addLabel('dateFrom', __('Date From'));
    $row->addDate('dateFrom')->setValue(Format::date($dateFrom))->required();

    $row = $form->addRow();
    $row->addLabel('dateTo', __('Date To'));
    $row->addDate('dateTo')->setValue(Format::date($dateTo))->required();

    $row = $form->addRow();
    $row->addSubmit(__('Generate Report'));

    echo $form->getOutput();

    // Display date range info
    echo '<p class="text-lg mb-4">' . __('Report Period') . ': <strong>' . Format::date($dateFrom) . '</strong> ' . __('to') . ' <strong>' . Format::date($dateTo) . '</strong></p>';

    // Generate report based on type
    if ($reportType === 'nutritional') {
        // Nutritional Summary Report
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Nutritional Summary') . '</h3>';

        // Get menu items for date range with nutritional info
        $menuData = $weeklyMenuGateway->getMenuForWeek($dateFrom, $gibbonSchoolYearID);
        $nutritionTotals = [
            'calories' => 0,
            'protein' => 0,
            'carbohydrates' => 0,
            'fat' => 0,
            'itemCount' => 0,
            'byMealType' => [],
        ];

        $menuItems = [];
        while ($row = $menuData->fetch()) {
            $menuItems[] = $row;
            $nutritionTotals['calories'] += floatval($row['calories'] ?? 0);
            $nutritionTotals['protein'] += floatval($row['protein'] ?? 0);
            $nutritionTotals['carbohydrates'] += floatval($row['carbohydrates'] ?? 0);
            $nutritionTotals['fat'] += floatval($row['fat'] ?? 0);
            $nutritionTotals['itemCount']++;

            $mealType = $row['mealType'];
            if (!isset($nutritionTotals['byMealType'][$mealType])) {
                $nutritionTotals['byMealType'][$mealType] = [
                    'calories' => 0,
                    'protein' => 0,
                    'carbohydrates' => 0,
                    'fat' => 0,
                    'count' => 0,
                ];
            }
            $nutritionTotals['byMealType'][$mealType]['calories'] += floatval($row['calories'] ?? 0);
            $nutritionTotals['byMealType'][$mealType]['protein'] += floatval($row['protein'] ?? 0);
            $nutritionTotals['byMealType'][$mealType]['carbohydrates'] += floatval($row['carbohydrates'] ?? 0);
            $nutritionTotals['byMealType'][$mealType]['fat'] += floatval($row['fat'] ?? 0);
            $nutritionTotals['byMealType'][$mealType]['count']++;
        }

        // Display summary cards
        echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
        echo '<h4 class="text-md font-semibold mb-3">' . __('Total Nutritional Values') . '</h4>';
        echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">';

        echo '<div class="bg-blue-50 rounded p-3">';
        echo '<span class="block text-sm font-medium text-blue-800">' . __('Calories') . '</span>';
        echo '<span class="block text-2xl font-bold text-blue-600">' . number_format($nutritionTotals['calories'], 0) . '</span>';
        echo '<span class="text-xs text-blue-500">kcal</span>';
        echo '</div>';

        echo '<div class="bg-green-50 rounded p-3">';
        echo '<span class="block text-sm font-medium text-green-800">' . __('Protein') . '</span>';
        echo '<span class="block text-2xl font-bold text-green-600">' . number_format($nutritionTotals['protein'], 1) . '</span>';
        echo '<span class="text-xs text-green-500">g</span>';
        echo '</div>';

        echo '<div class="bg-yellow-50 rounded p-3">';
        echo '<span class="block text-sm font-medium text-yellow-800">' . __('Carbohydrates') . '</span>';
        echo '<span class="block text-2xl font-bold text-yellow-600">' . number_format($nutritionTotals['carbohydrates'], 1) . '</span>';
        echo '<span class="text-xs text-yellow-500">g</span>';
        echo '</div>';

        echo '<div class="bg-red-50 rounded p-3">';
        echo '<span class="block text-sm font-medium text-red-800">' . __('Fat') . '</span>';
        echo '<span class="block text-2xl font-bold text-red-600">' . number_format($nutritionTotals['fat'], 1) . '</span>';
        echo '<span class="text-xs text-red-500">g</span>';
        echo '</div>';

        echo '</div>';
        echo '</div>';

        // Breakdown by meal type
        if (!empty($nutritionTotals['byMealType'])) {
            echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
            echo '<h4 class="text-md font-semibold mb-3">' . __('Breakdown by Meal Type') . '</h4>';
            echo '<div class="overflow-x-auto">';
            echo '<table class="min-w-full text-sm">';
            echo '<thead class="bg-gray-50">';
            echo '<tr>';
            echo '<th class="px-4 py-2 text-left">' . __('Meal Type') . '</th>';
            echo '<th class="px-4 py-2 text-right">' . __('Items') . '</th>';
            echo '<th class="px-4 py-2 text-right">' . __('Calories') . '</th>';
            echo '<th class="px-4 py-2 text-right">' . __('Protein') . '</th>';
            echo '<th class="px-4 py-2 text-right">' . __('Carbs') . '</th>';
            echo '<th class="px-4 py-2 text-right">' . __('Fat') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            $mealTypeOrder = ['Breakfast', 'Morning Snack', 'Lunch', 'Afternoon Snack', 'Dinner'];
            foreach ($mealTypeOrder as $mealType) {
                if (isset($nutritionTotals['byMealType'][$mealType])) {
                    $data = $nutritionTotals['byMealType'][$mealType];
                    echo '<tr class="border-t">';
                    echo '<td class="px-4 py-2 font-medium">' . __($mealType) . '</td>';
                    echo '<td class="px-4 py-2 text-right">' . $data['count'] . '</td>';
                    echo '<td class="px-4 py-2 text-right">' . number_format($data['calories'], 0) . ' kcal</td>';
                    echo '<td class="px-4 py-2 text-right">' . number_format($data['protein'], 1) . 'g</td>';
                    echo '<td class="px-4 py-2 text-right">' . number_format($data['carbohydrates'], 1) . 'g</td>';
                    echo '<td class="px-4 py-2 text-right">' . number_format($data['fat'], 1) . 'g</td>';
                    echo '</tr>';
                }
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            echo '</div>';
        }

        // Menu items list
        if (!empty($menuItems)) {
            echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
            echo '<h4 class="text-md font-semibold mb-3">' . __('Menu Items Detail') . '</h4>';
            echo '<div class="overflow-x-auto">';
            echo '<table class="min-w-full text-sm">';
            echo '<thead class="bg-gray-50">';
            echo '<tr>';
            echo '<th class="px-4 py-2 text-left">' . __('Date') . '</th>';
            echo '<th class="px-4 py-2 text-left">' . __('Meal') . '</th>';
            echo '<th class="px-4 py-2 text-left">' . __('Item') . '</th>';
            echo '<th class="px-4 py-2 text-right">' . __('Calories') . '</th>';
            echo '<th class="px-4 py-2 text-right">' . __('Protein') . '</th>';
            echo '<th class="px-4 py-2 text-right">' . __('Carbs') . '</th>';
            echo '<th class="px-4 py-2 text-right">' . __('Fat') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($menuItems as $item) {
                echo '<tr class="border-t">';
                echo '<td class="px-4 py-2">' . Format::date($item['date']) . '</td>';
                echo '<td class="px-4 py-2">' . __($item['mealType']) . '</td>';
                echo '<td class="px-4 py-2">' . htmlspecialchars($item['menuItemName']) . '</td>';
                echo '<td class="px-4 py-2 text-right">' . ($item['calories'] ? number_format($item['calories'], 0) : '-') . '</td>';
                echo '<td class="px-4 py-2 text-right">' . ($item['protein'] ? number_format($item['protein'], 1) . 'g' : '-') . '</td>';
                echo '<td class="px-4 py-2 text-right">' . ($item['carbohydrates'] ? number_format($item['carbohydrates'], 1) . 'g' : '-') . '</td>';
                echo '<td class="px-4 py-2 text-right">' . ($item['fat'] ? number_format($item['fat'], 1) . 'g' : '-') . '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
            echo __('No menu items scheduled for this period.');
            echo '</div>';
        }

    } elseif ($reportType === 'allergen') {
        // Allergen Exposure Report
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Allergen Exposure Report') . '</h3>';

        // Get allergen warnings for date range
        $currentDate = $dateFrom;
        $allWarnings = [];

        while ($currentDate <= $dateTo) {
            $warnings = $childDietaryGateway->getChildrenWithAllergyWarningsForDate($currentDate, $gibbonSchoolYearID);
            foreach ($warnings as $warning) {
                $warning['date'] = $currentDate;
                $allWarnings[] = $warning;
            }
            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }

        if (!empty($allWarnings)) {
            // Summary by allergen
            $allergenCounts = [];
            foreach ($allWarnings as $warning) {
                foreach ($warning['allergens'] as $allergen) {
                    $name = $allergen['allergen'];
                    if (!isset($allergenCounts[$name])) {
                        $allergenCounts[$name] = 0;
                    }
                    $allergenCounts[$name]++;
                }
            }
            arsort($allergenCounts);

            echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
            echo '<h4 class="text-md font-semibold mb-3">' . __('Allergen Exposure Summary') . '</h4>';
            echo '<div class="flex flex-wrap gap-2">';
            foreach ($allergenCounts as $allergen => $count) {
                $bgColor = $count > 5 ? 'bg-red-100 text-red-800' : ($count > 2 ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800');
                echo '<span class="px-3 py-1 rounded-full text-sm ' . $bgColor . '">';
                echo htmlspecialchars($allergen) . ': ' . $count . ' ' . __('exposure(s)');
                echo '</span>';
            }
            echo '</div>';
            echo '</div>';

            // Detailed warnings table
            echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
            echo '<h4 class="text-md font-semibold mb-3">' . __('Detailed Exposure List') . '</h4>';
            echo '<div class="overflow-x-auto">';
            echo '<table class="min-w-full text-sm">';
            echo '<thead class="bg-gray-50">';
            echo '<tr>';
            echo '<th class="px-4 py-2 text-left">' . __('Date') . '</th>';
            echo '<th class="px-4 py-2 text-left">' . __('Child') . '</th>';
            echo '<th class="px-4 py-2 text-left">' . __('Meal Type') . '</th>';
            echo '<th class="px-4 py-2 text-left">' . __('Menu Item') . '</th>';
            echo '<th class="px-4 py-2 text-left">' . __('Allergens') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody>';

            foreach ($allWarnings as $warning) {
                $childName = Format::name('', $warning['preferredName'], $warning['surname'], 'Student', false, true);
                $allergenList = array_map(function ($a) {
                    $severityColor = $a['severity'] === 'Severe' ? 'text-red-600' : ($a['severity'] === 'Moderate' ? 'text-yellow-600' : 'text-gray-600');
                    return '<span class="' . $severityColor . '">' . htmlspecialchars($a['allergen']) . '</span>';
                }, $warning['allergens']);

                echo '<tr class="border-t">';
                echo '<td class="px-4 py-2">' . Format::date($warning['date']) . '</td>';
                echo '<td class="px-4 py-2">' . htmlspecialchars($childName) . '</td>';
                echo '<td class="px-4 py-2">' . __($warning['mealType']) . '</td>';
                echo '<td class="px-4 py-2">' . htmlspecialchars($warning['menuItemName']) . '</td>';
                echo '<td class="px-4 py-2">' . implode(', ', $allergenList) . '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
            echo '</div>';
        } else {
            echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center text-green-700">';
            echo __('No allergen exposure warnings for this period.');
            echo '</div>';
        }

    } elseif ($reportType === 'dietary') {
        // Dietary Overview Report
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Dietary Overview') . '</h3>';

        // Get dietary type summary
        $dietarySummary = $childDietaryGateway->getDietarySummary($gibbonSchoolYearID);

        // Get allergen summary
        $allergenSummary = $childDietaryGateway->getAllergenSummary($gibbonSchoolYearID);

        // Dietary Types Summary
        echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
        echo '<h4 class="text-md font-semibold mb-3">' . __('Children by Dietary Type') . '</h4>';

        if (!empty($dietarySummary)) {
            echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3">';
            foreach ($dietarySummary as $diet) {
                $bgColor = 'bg-gray-50';
                if ($diet['dietaryType'] === 'Vegetarian') {
                    $bgColor = 'bg-green-50';
                } elseif ($diet['dietaryType'] === 'Vegan') {
                    $bgColor = 'bg-emerald-50';
                } elseif ($diet['dietaryType'] === 'Halal') {
                    $bgColor = 'bg-blue-50';
                } elseif ($diet['dietaryType'] === 'Kosher') {
                    $bgColor = 'bg-indigo-50';
                } elseif ($diet['dietaryType'] === 'Medical') {
                    $bgColor = 'bg-red-50';
                }

                echo '<div class="' . $bgColor . ' rounded p-3 text-center">';
                echo '<span class="block text-sm font-medium">' . __($diet['dietaryType']) . '</span>';
                echo '<span class="block text-2xl font-bold">' . $diet['childCount'] . '</span>';
                echo '<span class="text-xs text-gray-500">' . __('children') . '</span>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p class="text-gray-500">' . __('No dietary profile data available.') . '</p>';
        }
        echo '</div>';

        // Allergen Summary
        echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
        echo '<h4 class="text-md font-semibold mb-3">' . __('Children by Allergen') . '</h4>';

        if (!empty($allergenSummary)) {
            echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">';
            foreach ($allergenSummary as $allergen) {
                $bgColor = $allergen['childCount'] > 5 ? 'bg-red-50' : ($allergen['childCount'] > 2 ? 'bg-yellow-50' : 'bg-gray-50');

                echo '<div class="' . $bgColor . ' rounded p-3 text-center">';
                echo '<span class="block text-sm font-medium">' . htmlspecialchars($allergen['allergen']) . '</span>';
                echo '<span class="block text-2xl font-bold">' . $allergen['childCount'] . '</span>';
                echo '<span class="text-xs text-gray-500">' . __('children') . '</span>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p class="text-gray-500">' . __('No allergen data available.') . '</p>';
        }
        echo '</div>';

        // Menu coverage for selected period
        $menuSummary = $weeklyMenuGateway->getMenuSummaryByDateRange($dateFrom, $dateTo, $gibbonSchoolYearID);

        echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
        echo '<h4 class="text-md font-semibold mb-3">' . __('Menu Planning Coverage') . '</h4>';
        echo '<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">';

        echo '<div class="bg-purple-50 rounded p-3 text-center">';
        echo '<span class="block text-sm font-medium">' . __('Days Scheduled') . '</span>';
        echo '<span class="block text-2xl font-bold">' . ($menuSummary['daysScheduled'] ?? 0) . '</span>';
        echo '</div>';

        echo '<div class="bg-blue-50 rounded p-3 text-center">';
        echo '<span class="block text-sm font-medium">' . __('Breakfast') . '</span>';
        echo '<span class="block text-2xl font-bold">' . ($menuSummary['breakfastCount'] ?? 0) . '</span>';
        echo '</div>';

        echo '<div class="bg-green-50 rounded p-3 text-center">';
        echo '<span class="block text-sm font-medium">' . __('Morning Snack') . '</span>';
        echo '<span class="block text-2xl font-bold">' . ($menuSummary['morningSnackCount'] ?? 0) . '</span>';
        echo '</div>';

        echo '<div class="bg-yellow-50 rounded p-3 text-center">';
        echo '<span class="block text-sm font-medium">' . __('Lunch') . '</span>';
        echo '<span class="block text-2xl font-bold">' . ($menuSummary['lunchCount'] ?? 0) . '</span>';
        echo '</div>';

        echo '<div class="bg-orange-50 rounded p-3 text-center">';
        echo '<span class="block text-sm font-medium">' . __('Afternoon Snack') . '</span>';
        echo '<span class="block text-2xl font-bold">' . ($menuSummary['afternoonSnackCount'] ?? 0) . '</span>';
        echo '</div>';

        echo '<div class="bg-red-50 rounded p-3 text-center">';
        echo '<span class="block text-sm font-medium">' . __('Dinner') . '</span>';
        echo '<span class="block text-2xl font-bold">' . ($menuSummary['dinnerCount'] ?? 0) . '</span>';
        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

    // Link back to dashboard
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
}
