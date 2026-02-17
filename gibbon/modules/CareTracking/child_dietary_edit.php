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
use Gibbon\Module\CareTracking\Domain\ChildDietaryGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Care Tracking'), 'careTracking.php');
$page->breadcrumbs->add(__('Child Dietary Profiles'), 'child_dietary.php');
$page->breadcrumbs->add(__('Edit Dietary Profile'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/CareTracking/child_dietary.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get child person ID
    $gibbonPersonID = $_GET['gibbonPersonID'] ?? '';

    if (empty($gibbonPersonID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Get gateway via DI container
    $childDietaryGateway = $container->get(ChildDietaryGateway::class);

    // Get existing dietary profile
    $dietaryProfile = $childDietaryGateway->getDietaryByChild($gibbonPersonID);

    if (empty($dietaryProfile)) {
        $page->addError(__('The specified record cannot be found. This child may not have a dietary profile yet.'));
        echo '<div class="mt-4">';
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/child_dietary_add.php&gibbonPersonID=' . $gibbonPersonID . '" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Create Dietary Profile') . '</a>';
        echo '</div>';
        return;
    }

    // Get existing allergies as array
    $existingAllergies = [];
    $existingAllergenMap = [];
    if (!empty($dietaryProfile['allergies'])) {
        $allergies = json_decode($dietaryProfile['allergies'], true);
        if (is_array($allergies)) {
            $existingAllergies = $allergies;
            foreach ($allergies as $allergen) {
                $allergenName = is_array($allergen) && isset($allergen['allergen']) ? $allergen['allergen'] : (is_string($allergen) ? $allergen : '');
                $severity = is_array($allergen) && isset($allergen['severity']) ? $allergen['severity'] : 'Moderate';
                if (!empty($allergenName)) {
                    $existingAllergenMap[$allergenName] = $severity;
                }
            }
        }
    }

    // Options from gateway
    $dietaryTypeOptions = $childDietaryGateway->getDietaryTypeOptions();
    $allergenOptions = $childDietaryGateway->getAllergenOptions();
    $severityOptions = $childDietaryGateway->getSeverityOptions();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $URL = $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/child_dietary_edit.php&gibbonPersonID=' . $gibbonPersonID;

        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $session->get('csrf_token')) {
            $URL .= '&return=error0';
            header("Location: {$URL}");
            exit;
        }

        $currentUserID = $session->get('gibbonPersonID');

        // Get form data
        $dietaryType = $_POST['dietaryType'] ?? 'None';
        $restrictions = trim($_POST['restrictions'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $parentNotified = $_POST['parentNotified'] ?? 'N';

        // Process allergens
        $allergens = $_POST['allergens'] ?? [];
        $allergenSeverities = $_POST['allergenSeverity'] ?? [];
        $allergiesData = [];

        if (!empty($allergens) && is_array($allergens)) {
            foreach ($allergens as $allergen) {
                $severity = $allergenSeverities[$allergen] ?? 'Moderate';
                $allergiesData[] = [
                    'allergen' => $allergen,
                    'severity' => $severity,
                ];
            }
        }

        // Update dietary profile
        $success = $childDietaryGateway->updateDietaryProfile(
            $dietaryProfile['gibbonCareChildDietaryID'],
            $dietaryType,
            !empty($allergiesData) ? $allergiesData : null,
            $restrictions ?: null,
            $notes ?: null,
            $currentUserID
        );

        if (!$success) {
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        }

        // Update parent notification status if changed
        if ($parentNotified !== $dietaryProfile['parentNotified']) {
            $childDietaryGateway->setParentNotified(
                $dietaryProfile['gibbonCareChildDietaryID'],
                $parentNotified === 'Y'
            );
        }

        // Success - redirect to dietary profiles list
        $URL = $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/child_dietary.php&return=success1';
        header("Location: {$URL}");
        exit;
    }

    // Display return messages
    if (isset($_GET['return'])) {
        switch ($_GET['return']) {
            case 'success0':
                $page->addMessage(__('Dietary profile has been updated successfully.'));
                break;
            case 'error0':
                $page->addError(__('Your request failed because you do not have access to this action.'));
                break;
            case 'error1':
                $page->addError(__('Your request failed because your inputs were invalid.'));
                break;
            case 'error2':
                $page->addError(__('Your request failed due to a database error.'));
                break;
        }
    }

    // Child info header
    $childName = Format::name('', $dietaryProfile['preferredName'], $dietaryProfile['surname'], 'Student', true, true);
    $image = !empty($dietaryProfile['image_240']) ? $dietaryProfile['image_240'] : 'themes/Default/img/anonymous_240.jpg';

    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<div class="flex items-center gap-4">';
    echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-16 h-16 rounded-full object-cover" alt="' . htmlspecialchars($childName) . '">';
    echo '<div>';
    echo '<h2 class="text-lg font-semibold">' . htmlspecialchars($childName) . '</h2>';
    if (!empty($dietaryProfile['dob'])) {
        $age = date_diff(date_create($dietaryProfile['dob']), date_create('now'))->y;
        echo '<p class="text-sm text-gray-500">' . __('Age') . ': ' . $age . ' ' . __('years') . '</p>';
    }
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Create form
    $form = Form::create('editChildDietary', $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/child_dietary_edit.php&gibbonPersonID=' . $gibbonPersonID);
    $form->setTitle(__('Edit Dietary Profile'));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('csrf_token', $session->get('csrf_token'));

    // Dietary Information Section
    $form->addRow()->addHeading(__('Dietary Information'));

    // Dietary Type
    $row = $form->addRow();
        $row->addLabel('dietaryType', __('Dietary Type'))
            ->description(__('Select the primary dietary requirement for this child.'));
        $row->addSelect('dietaryType')
            ->fromArray($dietaryTypeOptions)
            ->required()
            ->selected($dietaryProfile['dietaryType'] ?? 'None');

    // Restrictions
    $row = $form->addRow();
        $row->addLabel('restrictions', __('Food Restrictions'))
            ->description(__('Describe any specific food restrictions (e.g., "No red meat", "Low sodium diet").'));
        $row->addTextArea('restrictions')
            ->setRows(3)
            ->maxLength(2000)
            ->setValue($dietaryProfile['restrictions'] ?? '');

    // Notes
    $row = $form->addRow();
        $row->addLabel('notes', __('Additional Notes'))
            ->description(__('Any additional information about the child\'s dietary needs.'));
        $row->addTextArea('notes')
            ->setRows(3)
            ->maxLength(2000)
            ->setValue($dietaryProfile['notes'] ?? '');

    // Allergens Section
    $form->addRow()->addHeading(__('Allergies'));

    // Allergen warning
    echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">';
    echo '<p class="text-sm text-red-800 font-medium">' . __('Important: Accurately record all allergies. This information is used to generate warnings when planning meals.') . '</p>';
    echo '</div>';

    // Allergen checkboxes with severity
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">';
    foreach ($allergenOptions as $value => $label) {
        $isChecked = isset($existingAllergenMap[$value]);
        $currentSeverity = $existingAllergenMap[$value] ?? 'Moderate';
        $checkedAttr = $isChecked ? ' checked' : '';
        $disabledAttr = $isChecked ? '' : ' disabled';

        $severityColors = [
            'Mild'     => 'yellow',
            'Moderate' => 'orange',
            'Severe'   => 'red',
        ];
        $indicatorColor = $severityColors[$currentSeverity] ?? 'gray';

        echo '<div class="bg-white border rounded-lg p-3' . ($isChecked ? ' border-' . $indicatorColor . '-300 bg-' . $indicatorColor . '-50' : '') . '">';
        echo '<label class="flex items-center mb-2">';
        echo '<input type="checkbox" name="allergens[]" value="' . htmlspecialchars($value) . '" class="allergenCheckbox mr-2" onchange="toggleSeverity(this)"' . $checkedAttr . '>';
        echo '<span class="font-medium">' . htmlspecialchars(__($label)) . '</span>';
        echo '</label>';
        echo '<select name="allergenSeverity[' . htmlspecialchars($value) . ']" class="allergenSeverity w-full border rounded px-2 py-1 text-sm"' . $disabledAttr . '>';
        foreach ($severityOptions as $sevValue => $sevLabel) {
            $selected = $sevValue === $currentSeverity ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($sevValue) . '"' . $selected . '>' . htmlspecialchars(__($sevLabel)) . '</option>';
        }
        echo '</select>';
        echo '</div>';
    }
    echo '</div>';

    // Severity legend
    echo '<div class="bg-gray-50 border rounded-lg p-4 mb-6">';
    echo '<h4 class="text-sm font-semibold text-gray-700 mb-2">' . __('Severity Levels') . '</h4>';
    echo '<div class="flex flex-wrap gap-4 text-sm">';
    echo '<div class="flex items-center"><span class="w-3 h-3 rounded-full bg-yellow-400 mr-2"></span>' . __('Mild') . ' - ' . __('Minor symptoms, usually not life-threatening') . '</div>';
    echo '<div class="flex items-center"><span class="w-3 h-3 rounded-full bg-orange-400 mr-2"></span>' . __('Moderate') . ' - ' . __('Significant symptoms requiring attention') . '</div>';
    echo '<div class="flex items-center"><span class="w-3 h-3 rounded-full bg-red-500 mr-2"></span>' . __('Severe') . ' - ' . __('Life-threatening, requires immediate action') . '</div>';
    echo '</div>';
    echo '</div>';

    // Parent Notification Section
    $form->addRow()->addHeading(__('Parent Notification'));

    // Parent notification status
    $row = $form->addRow();
        $row->addLabel('parentNotified', __('Parent Notified'))
            ->description(__('Has the parent been notified about this dietary profile?'));
        $row->addYesNo('parentNotified')
            ->required()
            ->selected($dietaryProfile['parentNotified'] ?? 'N');

    // Show notification date if notified
    if ($dietaryProfile['parentNotified'] === 'Y' && !empty($dietaryProfile['parentNotifiedTime'])) {
        echo '<div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4">';
        echo '<p class="text-sm text-green-800">';
        echo '<span class="font-medium">' . __('Parent was notified on') . ':</span> ';
        echo Format::dateTime($dietaryProfile['parentNotifiedTime']);
        echo '</p>';
        echo '</div>';
    }

    // Submit button
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Update Dietary Profile'));

    echo $form->getOutput();

    // JavaScript for allergen severity toggle
    echo '<script>
    function toggleSeverity(checkbox) {
        var container = checkbox.parentElement.parentElement;
        var select = container.querySelector(".allergenSeverity");
        if (checkbox.checked) {
            select.disabled = false;
            container.classList.add("border-orange-300", "bg-orange-50");
        } else {
            select.disabled = true;
            container.classList.remove("border-orange-300", "bg-orange-50", "border-yellow-300", "bg-yellow-50", "border-red-300", "bg-red-50");
        }
    }
    </script>';

    // Metadata section
    echo '<div class="bg-gray-50 border rounded-lg p-4 mt-4">';
    echo '<h4 class="text-sm font-semibold text-gray-700 mb-2">' . __('Record Information') . '</h4>';
    echo '<div class="grid grid-cols-2 gap-4 text-sm text-gray-600">';

    if (!empty($dietaryProfile['lastUpdatedByName'])) {
        echo '<div>';
        echo '<span class="font-medium">' . __('Last Updated By') . ':</span> ';
        echo htmlspecialchars(Format::name('', $dietaryProfile['lastUpdatedByName'], $dietaryProfile['lastUpdatedBySurname'], 'Staff', false, true));
        echo '</div>';
    }

    if (!empty($dietaryProfile['timestampCreated'])) {
        echo '<div>';
        echo '<span class="font-medium">' . __('Created') . ':</span> ';
        echo Format::dateTime($dietaryProfile['timestampCreated']);
        echo '</div>';
    }

    if (!empty($dietaryProfile['timestampModified'])) {
        echo '<div>';
        echo '<span class="font-medium">' . __('Last Modified') . ':</span> ';
        echo Format::dateTime($dietaryProfile['timestampModified']);
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';

    // Action buttons
    echo '<div class="flex justify-between items-center mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/child_dietary.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Child Dietary Profiles') . '</a>';
    echo '</div>';
}
