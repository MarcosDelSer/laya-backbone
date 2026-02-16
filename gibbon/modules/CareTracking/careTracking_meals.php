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
use Gibbon\Module\CareTracking\Domain\MealGateway;
use Gibbon\Module\CareTracking\Domain\AttendanceGateway;
use Gibbon\Module\AISync\AISyncService;
use Gibbon\Domain\System\SettingGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Care Tracking'), 'careTracking.php');
$page->breadcrumbs->add(__('Meals'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/CareTracking/careTracking_meals.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get date from request or default to today
    $date = $_GET['date'] ?? date('Y-m-d');

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    // Get gateways via DI container
    $mealGateway = $container->get(MealGateway::class);
    $attendanceGateway = $container->get(AttendanceGateway::class);

    // Get AI Sync service for webhook notifications
    try {
        $settingGateway = $container->get(SettingGateway::class);
        $aiSyncService = new AISyncService($settingGateway, $pdo);
    } catch (Exception $e) {
        $aiSyncService = null;
    }

    // Meal types and quantity options
    $mealTypes = [
        'Breakfast'        => __('Breakfast'),
        'Morning Snack'    => __('Morning Snack'),
        'Lunch'            => __('Lunch'),
        'Afternoon Snack'  => __('Afternoon Snack'),
        'Dinner'           => __('Dinner'),
    ];

    $quantityOptions = [
        'None'   => __('None'),
        'Little' => __('Little'),
        'Some'   => __('Some'),
        'Most'   => __('Most'),
        'All'    => __('All'),
    ];

    // Handle meal logging action
    $action = $_POST['action'] ?? '';
    $childID = $_POST['gibbonPersonID'] ?? null;

    if ($action === 'logMeal' && !empty($childID)) {
        $mealType = $_POST['mealType'] ?? '';
        $quantity = $_POST['quantity'] ?? 'Some';
        $allergyAlert = $_POST['allergyAlert'] ?? 'N';
        $notes = $_POST['notes'] ?? null;

        if (!empty($mealType)) {
            $result = $mealGateway->logMeal(
                $childID,
                $gibbonSchoolYearID,
                $date,
                $mealType,
                $quantity,
                $gibbonPersonID,
                $allergyAlert === 'Y',
                $notes
            );

            if ($result !== false) {
                $page->addSuccess(__('Meal has been logged successfully.'));

                // Trigger webhook for AI sync
                if ($aiSyncService !== null) {
                    try {
                        $mealData = [
                            'gibbonCareMealID' => $result,
                            'gibbonPersonID' => $childID,
                            'date' => $date,
                            'mealType' => $mealType,
                            'quantity' => $quantity,
                            'allergyAlert' => $allergyAlert,
                            'notes' => $notes,
                            'recordedByID' => $gibbonPersonID,
                        ];
                        $aiSyncService->syncMealEvent($result, $mealData);
                    } catch (Exception $e) {
                        // Silently fail - don't break UX if webhook fails
                    }
                }
            } else {
                $page->addError(__('Failed to log meal.'));
            }
        } else {
            $page->addError(__('Please select a meal type.'));
        }
    }

    // Handle bulk meal logging
    if ($action === 'logBulkMeal') {
        $mealType = $_POST['mealType'] ?? '';
        $childIDs = $_POST['childIDs'] ?? [];
        $quantity = $_POST['quantity'] ?? 'Some';
        $allergyAlert = $_POST['allergyAlert'] ?? 'N';
        $notes = $_POST['notes'] ?? null;

        if (!empty($mealType) && !empty($childIDs)) {
            $successCount = 0;
            foreach ($childIDs as $childID) {
                $result = $mealGateway->logMeal(
                    $childID,
                    $gibbonSchoolYearID,
                    $date,
                    $mealType,
                    $quantity,
                    $gibbonPersonID,
                    $allergyAlert === 'Y',
                    $notes
                );
                if ($result !== false) {
                    $successCount++;

                    // Trigger webhook for AI sync
                    if ($aiSyncService !== null) {
                        try {
                            $mealData = [
                                'gibbonCareMealID' => $result,
                                'gibbonPersonID' => $childID,
                                'date' => $date,
                                'mealType' => $mealType,
                                'quantity' => $quantity,
                                'allergyAlert' => $allergyAlert,
                                'notes' => $notes,
                                'recordedByID' => $gibbonPersonID,
                            ];
                            $aiSyncService->syncMealEvent($result, $mealData);
                        } catch (Exception $e) {
                            // Silently fail - don't break UX if webhook fails
                        }
                    }
                }
            }
            if ($successCount > 0) {
                $page->addSuccess(__('Meals logged for {count} children.', ['count' => $successCount]));
            }
        } else {
            $page->addError(__('Please select a meal type and at least one child.'));
        }
    }

    // Page header
    echo '<h2>' . __('Meal Logging') . '</h2>';

    // Date navigation form
    $form = Form::create('dateFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/CareTracking/careTracking_meals.php');

    $row = $form->addRow();
    $row->addLabel('date', __('Date'));
    $row->addDate('date')->setValue(Format::date($date))->required();

    $row = $form->addRow();
    $row->addSubmit(__('Go'));

    echo $form->getOutput();

    // Display formatted date
    echo '<p class="text-lg mb-4">' . __('Showing meals for') . ': <strong>' . Format::date($date) . '</strong></p>';

    // Get summary statistics
    $summary = $mealGateway->getMealSummaryByDate($gibbonSchoolYearID, $date);

    // Display summary
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Today\'s Meal Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">';

    if (!empty($summary) && is_array($summary)) {
        foreach ($summary as $meal) {
            $mealType = $meal['mealType'] ?? '';
            $total = $meal['totalRecords'] ?? 0;
            $ateAll = $meal['ateAll'] ?? 0;
            $ateNone = $meal['ateNone'] ?? 0;
            $allergyAlerts = $meal['allergyAlerts'] ?? 0;

            echo '<div class="bg-gray-50 rounded p-2">';
            echo '<span class="block text-sm font-medium">' . __($mealType) . '</span>';
            echo '<span class="block text-2xl font-bold">' . $total . '</span>';
            echo '<span class="text-xs text-gray-500">' . __('logged') . '</span>';
            if ($allergyAlerts > 0) {
                echo '<span class="block text-xs text-red-500">' . $allergyAlerts . ' ' . __('allergy alert(s)') . '</span>';
            }
            echo '</div>';
        }
    } else {
        echo '<div class="col-span-5 text-gray-500">' . __('No meals logged yet today.') . '</div>';
    }

    echo '</div>';
    echo '</div>';

    // Section: Quick Log Meal (for children currently checked in)
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Log Meal') . '</h3>';

    // Get children currently checked in
    $checkedInChildren = $attendanceGateway->selectChildrenCurrentlyCheckedIn($gibbonSchoolYearID, $date);

    if ($checkedInChildren->rowCount() > 0) {
        echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_meals.php&date=' . $date . '">';
        echo '<input type="hidden" name="action" value="logMeal">';

        // Meal type and quantity selection
        echo '<div class="mb-4 flex flex-wrap gap-4">';
        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Meal Type') . '</label>';
        echo '<select name="mealType" class="border rounded px-3 py-2" required>';
        echo '<option value="">' . __('Select Meal') . '</option>';
        foreach ($mealTypes as $value => $label) {
            echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Quantity Eaten') . '</label>';
        echo '<select name="quantity" class="border rounded px-3 py-2">';
        foreach ($quantityOptions as $value => $label) {
            $selected = $value === 'Some' ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div class="flex items-end">';
        echo '<label class="flex items-center text-sm">';
        echo '<input type="checkbox" name="allergyAlert" value="Y" class="mr-2">';
        echo __('Allergy Alert');
        echo '</label>';
        echo '</div>';

        echo '<div class="flex-1">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Notes') . '</label>';
        echo '<input type="text" name="notes" placeholder="' . __('Optional notes') . '" class="w-full border rounded px-3 py-2">';
        echo '</div>';
        echo '</div>';

        // Child selection grid
        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">';
        foreach ($checkedInChildren as $child) {
            $childName = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            $image = !empty($child['image_240']) ? $child['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            // Check what meals this child has already had today
            $childMeals = $mealGateway->selectMealsByPersonAndDate($child['gibbonPersonID'], $date);
            $mealsLogged = [];
            foreach ($childMeals as $meal) {
                $mealsLogged[] = $meal['mealType'];
            }
            $mealBadges = '';
            if (!empty($mealsLogged)) {
                $mealBadges = '<div class="flex flex-wrap gap-1 justify-center mt-1">';
                foreach ($mealsLogged as $mt) {
                    $shortName = substr($mt, 0, 1);
                    $mealBadges .= '<span class="bg-green-200 text-green-800 text-xs px-1 rounded" title="' . htmlspecialchars($mt) . '">' . $shortName . '</span>';
                }
                $mealBadges .= '</div>';
            }

            echo '<div class="bg-white rounded-lg shadow p-3 text-center hover:shadow-lg transition-shadow">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-16 h-16 rounded-full mx-auto mb-2 object-cover" alt="' . htmlspecialchars($childName) . '">';
            echo '<p class="text-sm font-medium truncate">' . htmlspecialchars($childName) . '</p>';
            echo $mealBadges;
            echo '<button type="submit" name="gibbonPersonID" value="' . $child['gibbonPersonID'] . '" class="mt-2 bg-green-500 text-white text-xs px-3 py-1 rounded hover:bg-green-600">' . __('Log Meal') . '</button>';
            echo '</div>';
        }
        echo '</div>';

        echo '</form>';
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 mb-4">' . __('No children are currently checked in.') . '</p>';
    }

    // Section: Bulk Meal Logging
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Bulk Meal Logging') . '</h3>';

    if ($checkedInChildren->rowCount() > 0) {
        // Reset the result pointer
        $checkedInChildren = $attendanceGateway->selectChildrenCurrentlyCheckedIn($gibbonSchoolYearID, $date);

        echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_meals.php&date=' . $date . '">';
        echo '<input type="hidden" name="action" value="logBulkMeal">';

        echo '<p class="text-sm text-gray-600 mb-3">' . __('Select multiple children to log the same meal for all at once.') . '</p>';

        // Meal type and quantity selection
        echo '<div class="mb-4 flex flex-wrap gap-4">';
        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Meal Type') . '</label>';
        echo '<select name="mealType" class="border rounded px-3 py-2" required>';
        echo '<option value="">' . __('Select Meal') . '</option>';
        foreach ($mealTypes as $value => $label) {
            echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Quantity Eaten') . '</label>';
        echo '<select name="quantity" class="border rounded px-3 py-2">';
        foreach ($quantityOptions as $value => $label) {
            $selected = $value === 'Some' ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div class="flex items-end">';
        echo '<label class="flex items-center text-sm">';
        echo '<input type="checkbox" name="allergyAlert" value="Y" class="mr-2">';
        echo __('Allergy Alert');
        echo '</label>';
        echo '</div>';

        echo '<div class="flex-1">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Notes') . '</label>';
        echo '<input type="text" name="notes" placeholder="' . __('Optional notes') . '" class="w-full border rounded px-3 py-2">';
        echo '</div>';
        echo '</div>';

        // Child selection with checkboxes
        echo '<div class="mb-3">';
        echo '<label class="flex items-center text-sm font-medium mb-2">';
        echo '<input type="checkbox" id="selectAll" class="mr-2">';
        echo __('Select All');
        echo '</label>';
        echo '</div>';

        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">';
        foreach ($checkedInChildren as $child) {
            $childName = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            $image = !empty($child['image_240']) ? $child['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            echo '<label class="bg-white rounded-lg shadow p-3 text-center hover:shadow-lg transition-shadow cursor-pointer">';
            echo '<input type="checkbox" name="childIDs[]" value="' . $child['gibbonPersonID'] . '" class="childCheckbox mb-2">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-12 h-12 rounded-full mx-auto mb-2 object-cover" alt="' . htmlspecialchars($childName) . '">';
            echo '<p class="text-sm font-medium truncate">' . htmlspecialchars($childName) . '</p>';
            echo '</label>';
        }
        echo '</div>';

        echo '<div class="mt-4">';
        echo '<button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Log Meal for Selected') . '</button>';
        echo '</div>';

        echo '</form>';

        // JavaScript for Select All functionality
        echo '<script>
        document.getElementById("selectAll").addEventListener("change", function() {
            var checkboxes = document.getElementsByClassName("childCheckbox");
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = this.checked;
            }
        });
        </script>';

        echo '</div>';
    }

    // Section: Today's Meal Records
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Meal Records') . '</h3>';

    // Build query criteria
    $criteria = $mealGateway->newQueryCriteria()
        ->sortBy(['mealType', 'surname', 'preferredName'])
        ->fromPOST();

    // Get meal data for the date
    $meals = $mealGateway->queryMealsByDate($criteria, $gibbonSchoolYearID, $date);

    // Build DataTable
    $table = DataTable::createPaginated('meals', $criteria);
    $table->setTitle(__('Meal Records'));

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

    $table->addColumn('mealType', __('Meal Type'))
        ->sortable()
        ->format(function ($row) {
            return __($row['mealType']);
        });

    $table->addColumn('quantity', __('Quantity'))
        ->sortable()
        ->format(function ($row) {
            $colors = [
                'None'   => 'red',
                'Little' => 'orange',
                'Some'   => 'yellow',
                'Most'   => 'blue',
                'All'    => 'green',
            ];
            $color = $colors[$row['quantity']] ?? 'gray';
            return '<span class="bg-' . $color . '-100 text-' . $color . '-800 text-xs px-2 py-1 rounded">' . __($row['quantity']) . '</span>';
        });

    $table->addColumn('allergyAlert', __('Allergy'))
        ->notSortable()
        ->format(function ($row) {
            if ($row['allergyAlert'] === 'Y') {
                return '<span class="text-red-600 font-bold" title="' . __('Allergy Alert') . '">!</span>';
            }
            return '-';
        });

    $table->addColumn('notes', __('Notes'))
        ->format(function ($row) {
            if (empty($row['notes'])) {
                return '-';
            }
            return '<span class="text-sm text-gray-600" title="' . htmlspecialchars($row['notes']) . '">' .
                   htmlspecialchars(substr($row['notes'], 0, 30)) .
                   (strlen($row['notes']) > 30 ? '...' : '') . '</span>';
        });

    $table->addColumn('time', __('Logged'))
        ->notSortable()
        ->format(function ($row) {
            return Format::time($row['timestampCreated']);
        });

    // Output table
    if ($meals->count() > 0) {
        echo $table->render($meals);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        echo __('No meal records found for this date.');
        echo '</div>';
    }

    // Link back to dashboard
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking.php&date=' . $date . '" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
}
