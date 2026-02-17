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
use Gibbon\Module\CareTracking\Domain\ChildDietaryGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Care Tracking'), 'careTracking.php');
$page->breadcrumbs->add(__('Child Dietary Profiles'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/CareTracking/child_dietary.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get gateway via DI container
    $childDietaryGateway = $container->get(ChildDietaryGateway::class);

    // Get filter parameters
    $dietaryType = $_GET['dietaryType'] ?? '';
    $hasAllergies = $_GET['hasAllergies'] ?? '';

    // Dietary type options
    $dietaryTypeOptions = [
        ''           => __('All Dietary Types'),
        'None'       => __('None'),
        'Vegetarian' => __('Vegetarian'),
        'Vegan'      => __('Vegan'),
        'Halal'      => __('Halal'),
        'Kosher'     => __('Kosher'),
        'Medical'    => __('Medical'),
        'Other'      => __('Other'),
    ];

    // Page header with Add button
    echo '<div class="flex justify-between items-center mb-4">';
    echo '<h2>' . __('Child Dietary Profiles') . '</h2>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/child_dietary_add.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Add Dietary Profile') . '</a>';
    echo '</div>';

    // Filter form
    $form = Form::create('childDietaryFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/CareTracking/child_dietary.php');

    $row = $form->addRow();
    $row->addLabel('dietaryType', __('Dietary Type'));
    $row->addSelect('dietaryType')
        ->fromArray($dietaryTypeOptions)
        ->selected($dietaryType);

    $row = $form->addRow();
    $row->addLabel('hasAllergies', __('Has Allergies'));
    $row->addCheckbox('hasAllergies')
        ->setValue('Y')
        ->checked($hasAllergies === 'Y');

    $row = $form->addRow();
    $row->addSearchSubmit($gibbon->session, __('Clear Filters'));

    echo $form->getOutput();

    // Build query criteria
    $criteria = $childDietaryGateway->newQueryCriteria()
        ->sortBy(['surname', 'preferredName'])
        ->fromPOST();

    // Apply filters
    if (!empty($dietaryType)) {
        $criteria->filterBy('dietaryType', $dietaryType);
    }
    if ($hasAllergies === 'Y') {
        $criteria->filterBy('hasAllergies', 'Y');
    }

    // Get children with dietary profiles (includes those without profiles)
    $children = $childDietaryGateway->queryChildrenWithDietary($criteria, $gibbonSchoolYearID);

    // Build DataTable
    $table = DataTable::createPaginated('childDietary', $criteria);
    $table->setTitle(__('Children with Dietary Profiles'));

    // Add columns
    $table->addColumn('image', __('Photo'))
        ->notSortable()
        ->format(function ($row) use ($session) {
            $image = !empty($row['image_240']) ? $row['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            return '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-10 h-10 rounded-full object-cover" alt="">';
        });

    $table->addColumn('name', __('Name'))
        ->sortable(['surname', 'preferredName'])
        ->format(function ($row) {
            return Format::name('', $row['preferredName'], $row['surname'], 'Student', true, true);
        });

    $table->addColumn('dietaryType', __('Dietary Type'))
        ->sortable()
        ->format(function ($row) {
            if (empty($row['dietaryType']) || $row['dietaryType'] === 'None') {
                return '<span class="text-gray-400 text-xs">' . __('None') . '</span>';
            }
            $typeColors = [
                'Vegetarian' => 'green',
                'Vegan'      => 'emerald',
                'Halal'      => 'blue',
                'Kosher'     => 'indigo',
                'Medical'    => 'red',
                'Other'      => 'gray',
            ];
            $color = $typeColors[$row['dietaryType']] ?? 'gray';
            return '<span class="bg-' . $color . '-100 text-' . $color . '-800 text-xs px-2 py-1 rounded">' . __($row['dietaryType']) . '</span>';
        });

    $table->addColumn('allergies', __('Allergies'))
        ->notSortable()
        ->format(function ($row) {
            if (empty($row['allergies'])) {
                return '<span class="text-gray-400 text-xs">' . __('None') . '</span>';
            }
            $allergies = json_decode($row['allergies'], true);
            if (empty($allergies) || !is_array($allergies)) {
                return '<span class="text-gray-400 text-xs">' . __('None') . '</span>';
            }
            $badges = [];
            foreach ($allergies as $allergen) {
                $allergenName = is_array($allergen) && isset($allergen['allergen']) ? $allergen['allergen'] : (is_string($allergen) ? $allergen : '');
                $severity = is_array($allergen) && isset($allergen['severity']) ? $allergen['severity'] : 'Moderate';
                $severityColors = [
                    'Severe'   => 'red',
                    'Moderate' => 'orange',
                    'Mild'     => 'yellow',
                ];
                $color = $severityColors[$severity] ?? 'gray';
                $badges[] = '<span class="bg-' . $color . '-100 text-' . $color . '-800 text-xs px-1.5 py-0.5 rounded" title="' . htmlspecialchars(__($severity)) . '">' . htmlspecialchars($allergenName) . '</span>';
            }
            return '<div class="flex flex-wrap gap-1">' . implode('', $badges) . '</div>';
        });

    $table->addColumn('restrictions', __('Restrictions'))
        ->notSortable()
        ->format(function ($row) {
            if (empty($row['restrictions'])) {
                return '<span class="text-gray-400 text-xs">' . __('None') . '</span>';
            }
            $text = htmlspecialchars(substr($row['restrictions'], 0, 40));
            if (strlen($row['restrictions']) > 40) {
                $text .= '...';
            }
            return '<span class="text-sm text-gray-600" title="' . htmlspecialchars($row['restrictions']) . '">' . $text . '</span>';
        });

    $table->addColumn('notes', __('Notes'))
        ->notSortable()
        ->format(function ($row) {
            if (empty($row['notes'])) {
                return '-';
            }
            $text = htmlspecialchars(substr($row['notes'], 0, 30));
            if (strlen($row['notes']) > 30) {
                $text .= '...';
            }
            return '<span class="text-sm text-gray-600" title="' . htmlspecialchars($row['notes']) . '">' . $text . '</span>';
        });

    $table->addColumn('parentNotified', __('Parent Notified'))
        ->notSortable()
        ->format(function ($row) {
            if (empty($row['gibbonCareChildDietaryID'])) {
                return '-';
            }
            if ($row['parentNotified'] === 'Y') {
                return '<span class="text-green-600 font-medium" title="' . __('Parent has been notified') . '">✓</span>';
            }
            return '<span class="text-yellow-600 font-medium" title="' . __('Parent not yet notified') . '">!</span>';
        });

    // Add action column
    $table->addActionColumn()
        ->addParam('gibbonPersonID')
        ->format(function ($row, $actions) {
            if (!empty($row['gibbonCareChildDietaryID'])) {
                $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/CareTracking/child_dietary_edit.php');
            } else {
                $actions->addAction('add', __('Add Profile'))
                    ->setIcon('iconAdd')
                    ->setURL('/modules/CareTracking/child_dietary_add.php');
            }
        });

    // Output table
    if ($children->count() > 0) {
        echo $table->render($children);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        echo __('No children found matching the selected criteria.');
        echo '</div>';
    }

    // Summary statistics
    $dietarySummary = $childDietaryGateway->getDietarySummary($gibbonSchoolYearID);
    $allergenSummary = $childDietaryGateway->getAllergenSummary($gibbonSchoolYearID);

    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">';

    // Dietary Type Summary
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Children by Dietary Type') . '</h3>';
    if (!empty($dietarySummary)) {
        echo '<div class="space-y-2">';
        foreach ($dietarySummary as $summary) {
            $type = $summary['dietaryType'] ?? 'None';
            $count = $summary['childCount'] ?? 0;
            $percentage = $children->count() > 0 ? round(($count / $children->count()) * 100, 1) : 0;

            $typeColors = [
                'None'       => 'gray',
                'Vegetarian' => 'green',
                'Vegan'      => 'emerald',
                'Halal'      => 'blue',
                'Kosher'     => 'indigo',
                'Medical'    => 'red',
                'Other'      => 'purple',
            ];
            $color = $typeColors[$type] ?? 'gray';

            echo '<div class="flex items-center justify-between">';
            echo '<div class="flex items-center">';
            echo '<span class="w-3 h-3 rounded-full bg-' . $color . '-500 mr-2"></span>';
            echo '<span class="text-sm">' . __($type) . '</span>';
            echo '</div>';
            echo '<div class="flex items-center">';
            echo '<span class="font-medium mr-2">' . $count . '</span>';
            echo '<span class="text-xs text-gray-500">(' . $percentage . '%)</span>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 text-sm">' . __('No dietary profiles recorded yet.') . '</p>';
    }
    echo '</div>';

    // Allergen Summary
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Children by Allergen') . '</h3>';
    if (!empty($allergenSummary)) {
        echo '<div class="space-y-2">';
        $displayCount = 0;
        foreach ($allergenSummary as $summary) {
            if ($displayCount >= 8) {
                $remaining = count($allergenSummary) - $displayCount;
                echo '<div class="text-xs text-gray-500">' . __('and {count} more allergens...', ['count' => $remaining]) . '</div>';
                break;
            }
            $allergen = $summary['allergen'] ?? '';
            $count = $summary['childCount'] ?? 0;

            echo '<div class="flex items-center justify-between">';
            echo '<span class="text-sm">' . htmlspecialchars(__($allergen)) . '</span>';
            echo '<span class="bg-red-100 text-red-800 text-xs px-2 py-0.5 rounded">' . $count . ' ' . ($count === 1 ? __('child') : __('children')) . '</span>';
            echo '</div>';
            $displayCount++;
        }
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 text-sm">' . __('No allergies recorded yet.') . '</p>';
    }
    echo '</div>';

    echo '</div>';

    // Quick stats bar
    $totalChildren = $children->count();
    $withProfiles = 0;
    $withAllergies = 0;
    $pendingNotification = 0;

    foreach ($children as $child) {
        if (!empty($child['gibbonCareChildDietaryID'])) {
            $withProfiles++;
            if (!empty($child['allergies'])) {
                $withAllergies++;
            }
            if ($child['parentNotified'] !== 'Y') {
                $pendingNotification++;
            }
        }
    }

    echo '<div class="bg-white rounded-lg shadow p-4 mt-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Quick Statistics') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">';

    echo '<div class="bg-gray-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold">' . $totalChildren . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Total Children') . '</span>';
    echo '</div>';

    echo '<div class="bg-blue-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold text-blue-600">' . $withProfiles . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('With Profiles') . '</span>';
    echo '</div>';

    echo '<div class="bg-orange-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold text-orange-600">' . $withAllergies . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('With Allergies') . '</span>';
    echo '</div>';

    echo '<div class="bg-yellow-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold text-yellow-600">' . $pendingNotification . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Pending Notification') . '</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Link back to Care Tracking dashboard
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Care Tracking') . '</a>';
    echo '</div>';
}
