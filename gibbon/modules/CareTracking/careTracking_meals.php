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
use Gibbon\Module\MedicalTracking\Domain\AllergyGateway;

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
    $allergyGateway = $container->get(AllergyGateway::class);

    // Helper function to get allergy warning HTML for a child
    $getAllergyWarningHTML = function ($gibbonPersonID) use ($allergyGateway) {
        $allergies = $allergyGateway->selectFoodAllergiesByPerson($gibbonPersonID);
        if ($allergies->rowCount() === 0) {
            return '';
        }

        $allergyList = [];
        $hasEpiPen = false;
        $hasSevere = false;

        foreach ($allergies as $allergy) {
            $allergyList[] = htmlspecialchars($allergy['allergenName']);
            if ($allergy['epiPenRequired'] === 'Y') {
                $hasEpiPen = true;
            }
            if (in_array($allergy['severity'], ['Severe', 'Life-Threatening'])) {
                $hasSevere = true;
            }
        }

        $bgColor = $hasSevere ? 'bg-red-100 border-red-400' : 'bg-yellow-100 border-yellow-400';
        $textColor = $hasSevere ? 'text-red-700' : 'text-yellow-700';
        $icon = $hasSevere ? '&#9888;' : '&#9888;';

        $html = '<div class="' . $bgColor . ' border rounded p-1 mt-1 text-xs ' . $textColor . '">';
        $html .= '<span class="font-semibold">' . $icon . ' ' . __('Allergies') . ':</span> ';
        $html .= implode(', ', $allergyList);
        if ($hasEpiPen) {
            $html .= ' <span class="bg-red-500 text-white px-1 rounded text-xs font-bold" title="' . __('EpiPen Required') . '">EpiPen</span>';
        }
        $html .= '</div>';

        return $html;
    };

    // Helper function to get compact allergy badge for a child
    $getAllergyBadgeHTML = function ($gibbonPersonID) use ($allergyGateway) {
        $allergies = $allergyGateway->selectFoodAllergiesByPerson($gibbonPersonID);
        if ($allergies->rowCount() === 0) {
            return '';
        }

        $hasEpiPen = false;
        $hasSevere = false;
        $allergyNames = [];

        foreach ($allergies as $allergy) {
            $allergyNames[] = $allergy['allergenName'];
            if ($allergy['epiPenRequired'] === 'Y') {
                $hasEpiPen = true;
            }
            if (in_array($allergy['severity'], ['Severe', 'Life-Threatening'])) {
                $hasSevere = true;
            }
        }

        $bgColor = $hasSevere ? 'bg-red-500' : 'bg-yellow-500';
        $title = __('Allergies') . ': ' . htmlspecialchars(implode(', ', $allergyNames));
        if ($hasEpiPen) {
            $title .= ' (' . __('EpiPen Required') . ')';
        }

        $html = '<span class="' . $bgColor . ' text-white text-xs px-1 rounded font-bold" title="' . $title . '">&#9888;</span>';

        return $html;
    };

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
        $menuItemID = $_POST['gibbonCareMenuItemID'] ?? null;

        if (!empty($mealType)) {
            // Auto-flag allergens if menu item is selected
            $autoAllergyAlert = false;
            $allergenNote = null;
            if (!empty($menuItemID)) {
                $conflicts = $mealGateway->checkAllergenAlertForChild($childID, $menuItemID);
                if (!empty($conflicts)) {
                    $autoAllergyAlert = true;
                    $allergenNames = array_column($conflicts, 'allergen');
                    $allergenNote = __('Auto-flagged allergens: ') . implode(', ', $allergenNames);
                    // Append allergen note to existing notes
                    if (!empty($notes)) {
                        $notes .= ' | ' . $allergenNote;
                    } else {
                        $notes = $allergenNote;
                    }
                }
            }

            // Use auto-flagged allergy alert if detected, otherwise use manual selection
            $finalAllergyAlert = $autoAllergyAlert || $allergyAlert === 'Y';

            // Log meal with or without menu item reference
            if (!empty($menuItemID)) {
                $result = $mealGateway->logMealWithMenuItem(
                    $childID,
                    $gibbonSchoolYearID,
                    $date,
                    $mealType,
                    $menuItemID,
                    $quantity,
                    $gibbonPersonID,
                    $finalAllergyAlert,
                    $notes
                );
            } else {
                $result = $mealGateway->logMeal(
                    $childID,
                    $gibbonSchoolYearID,
                    $date,
                    $mealType,
                    $quantity,
                    $gibbonPersonID,
                    $finalAllergyAlert,
                    $notes
                );
            }

            if ($result !== false) {
                if ($autoAllergyAlert) {
                    $page->addWarning(__('Meal logged with allergy alert! Allergens detected: {allergens}', ['allergens' => implode(', ', $allergenNames)]));
                } else {
                    $page->addSuccess(__('Meal has been logged successfully.'));
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
        $menuItemID = $_POST['gibbonCareMenuItemID'] ?? null;

        if (!empty($mealType) && !empty($childIDs)) {
            $successCount = 0;
            $allergyAlertCount = 0;

            // Pre-fetch children with allergies to menu item for bulk operations
            $childrenWithAllergies = [];
            if (!empty($menuItemID)) {
                $childrenWithAllergies = $mealGateway->getChildrenWithAllergyToItem($menuItemID, $gibbonSchoolYearID);
            }

            foreach ($childIDs as $childID) {
                // Check if this child has allergies to the menu item
                $autoAllergyAlert = false;
                $childNotes = $notes;

                if (!empty($menuItemID) && isset($childrenWithAllergies[$childID])) {
                    $autoAllergyAlert = true;
                    $conflicts = $childrenWithAllergies[$childID]['conflicts'] ?? [];
                    $allergenNames = array_column($conflicts, 'allergen');
                    $allergenNote = __('Auto-flagged allergens: ') . implode(', ', $allergenNames);
                    if (!empty($childNotes)) {
                        $childNotes .= ' | ' . $allergenNote;
                    } else {
                        $childNotes = $allergenNote;
                    }
                }

                // Use auto-flagged allergy alert if detected, otherwise use manual selection
                $finalAllergyAlert = $autoAllergyAlert || $allergyAlert === 'Y';

                // Log meal with or without menu item reference
                if (!empty($menuItemID)) {
                    $result = $mealGateway->logMealWithMenuItem(
                        $childID,
                        $gibbonSchoolYearID,
                        $date,
                        $mealType,
                        $menuItemID,
                        $quantity,
                        $gibbonPersonID,
                        $finalAllergyAlert,
                        $childNotes
                    );
                } else {
                    $result = $mealGateway->logMeal(
                        $childID,
                        $gibbonSchoolYearID,
                        $date,
                        $mealType,
                        $quantity,
                        $gibbonPersonID,
                        $finalAllergyAlert,
                        $childNotes
                    );
                }

                if ($result !== false) {
                    $successCount++;
                    if ($autoAllergyAlert) {
                        $allergyAlertCount++;
                    }
                }
            }

            if ($successCount > 0) {
                if ($allergyAlertCount > 0) {
                    $page->addWarning(__('Meals logged for {count} children. {alerts} had allergy alerts auto-flagged.', [
                        'count' => $successCount,
                        'alerts' => $allergyAlertCount,
                    ]));
                } else {
                    $page->addSuccess(__('Meals logged for {count} children.', ['count' => $successCount]));
                }
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

    // Display allergy warnings for children currently checked in
    $childrenWithAllergies = [];
    $tempCheckedIn = $attendanceGateway->selectChildrenCurrentlyCheckedIn($gibbonSchoolYearID, $date);

    foreach ($tempCheckedIn as $child) {
        $allergies = $allergyGateway->selectFoodAllergiesByPerson($child['gibbonPersonID']);
        if ($allergies->rowCount() > 0) {
            $allergyData = [];
            $hasEpiPen = false;
            $hasSevere = false;

            foreach ($allergies as $allergy) {
                $allergyData[] = [
                    'name' => $allergy['allergenName'],
                    'severity' => $allergy['severity'],
                    'epiPen' => $allergy['epiPenRequired'],
                    'treatment' => $allergy['treatment'],
                ];
                if ($allergy['epiPenRequired'] === 'Y') {
                    $hasEpiPen = true;
                }
                if (in_array($allergy['severity'], ['Severe', 'Life-Threatening'])) {
                    $hasSevere = true;
                }
            }

            $childrenWithAllergies[] = [
                'gibbonPersonID' => $child['gibbonPersonID'],
                'preferredName' => $child['preferredName'],
                'surname' => $child['surname'],
                'image_240' => $child['image_240'],
                'allergies' => $allergyData,
                'hasEpiPen' => $hasEpiPen,
                'hasSevere' => $hasSevere,
            ];
        }
    }

    // Display allergy warning banner if there are children with allergies
    if (!empty($childrenWithAllergies)) {
        echo '<div class="bg-red-50 border border-red-300 rounded-lg p-4 mb-4">';
        echo '<h3 class="text-lg font-semibold text-red-700 mb-3">&#9888; ' . __('Children with Food Allergies Present') . '</h3>';
        echo '<p class="text-sm text-red-600 mb-3">' . __('The following children checked in today have food allergies. Please verify meal ingredients before serving.') . '</p>';
        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">';

        foreach ($childrenWithAllergies as $child) {
            $childName = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            $image = !empty($child['image_240']) ? $child['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            $cardBg = $child['hasSevere'] ? 'bg-red-100 border-red-400' : 'bg-yellow-50 border-yellow-300';
            $borderStyle = $child['hasSevere'] ? 'border-l-4 border-l-red-500' : 'border-l-4 border-l-yellow-500';

            echo '<div class="' . $cardBg . ' rounded p-3 ' . $borderStyle . '">';
            echo '<div class="flex items-start">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-12 h-12 rounded-full object-cover mr-3 flex-shrink-0" alt="">';
            echo '<div class="flex-1 min-w-0">';
            echo '<p class="font-semibold text-sm">' . htmlspecialchars($childName) . '</p>';

            // List allergies
            echo '<div class="mt-1">';
            foreach ($child['allergies'] as $allergy) {
                $severityColor = in_array($allergy['severity'], ['Severe', 'Life-Threatening']) ? 'text-red-700 font-bold' : 'text-orange-600';
                echo '<span class="block text-xs ' . $severityColor . '">';
                echo '&#8226; ' . htmlspecialchars($allergy['name']);
                echo ' <span class="text-gray-500">(' . __($allergy['severity']) . ')</span>';
                echo '</span>';
            }
            echo '</div>';

            // EpiPen indicator
            if ($child['hasEpiPen']) {
                echo '<span class="inline-block mt-1 bg-red-600 text-white text-xs px-2 py-0.5 rounded font-bold">' . __('EpiPen Required') . '</span>';
            }

            echo '</div>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
        echo '<p class="text-xs text-red-500 mt-3"><strong>' . __('Important') . ':</strong> ' . __('Always check meal ingredients and consult the medical tracking module for detailed allergy information and treatment protocols.') . '</p>';
        echo '</div>';
    }

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

            // Get allergy information for this child
            $allergyWarning = $getAllergyWarningHTML($child['gibbonPersonID']);
            $hasAllergy = !empty($allergyWarning);
            $cardBorder = $hasAllergy ? ' border-2 border-red-400' : '';

            echo '<div class="bg-white rounded-lg shadow p-3 text-center hover:shadow-lg transition-shadow' . $cardBorder . '">';
            echo '<div class="relative">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-16 h-16 rounded-full mx-auto mb-2 object-cover" alt="' . htmlspecialchars($childName) . '">';
            if ($hasAllergy) {
                echo '<span class="absolute top-0 right-1/4 bg-red-500 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center font-bold" title="' . __('Has Food Allergies') . '">&#9888;</span>';
            }
            echo '</div>';
            echo '<p class="text-sm font-medium truncate">' . htmlspecialchars($childName) . '</p>';
            echo $allergyWarning;
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

            // Get allergy badge for this child
            $allergyBadge = $getAllergyBadgeHTML($child['gibbonPersonID']);
            $hasAllergy = !empty($allergyBadge);
            $cardBorder = $hasAllergy ? ' border-2 border-red-400' : '';

            echo '<label class="bg-white rounded-lg shadow p-3 text-center hover:shadow-lg transition-shadow cursor-pointer' . $cardBorder . '">';
            echo '<input type="checkbox" name="childIDs[]" value="' . $child['gibbonPersonID'] . '" class="childCheckbox mb-2">';
            echo '<div class="relative inline-block">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-12 h-12 rounded-full mx-auto mb-2 object-cover" alt="' . htmlspecialchars($childName) . '">';
            if ($hasAllergy) {
                echo '<span class="absolute -top-1 -right-1">' . $allergyBadge . '</span>';
            }
            echo '</div>';
            echo '<p class="text-sm font-medium truncate">' . htmlspecialchars($childName) . '</p>';
            if ($hasAllergy) {
                echo '<span class="text-xs text-red-600">' . __('Has Allergies') . '</span>';
            }
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
