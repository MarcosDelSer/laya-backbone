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
use Gibbon\FileUploader;
use Gibbon\Services\Format;
use Gibbon\Module\CareTracking\Domain\MenuItemGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Care Tracking'), 'careTracking.php');
$page->breadcrumbs->add(__('Menu Items'), 'menu_items.php');
$page->breadcrumbs->add(__('Add Menu Item'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/CareTracking/menu_items.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get gateway via DI container
    $menuItemGateway = $container->get(MenuItemGateway::class);

    // Category options
    $categoryOptions = [
        ''             => __('Please select...'),
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

    // Allergen options
    $allergenOptions = [
        'Milk'       => __('Milk'),
        'Eggs'       => __('Eggs'),
        'Peanuts'    => __('Peanuts'),
        'Tree Nuts'  => __('Tree Nuts'),
        'Fish'       => __('Fish'),
        'Shellfish'  => __('Shellfish'),
        'Wheat'      => __('Wheat'),
        'Soy'        => __('Soy'),
        'Sesame'     => __('Sesame'),
        'Gluten'     => __('Gluten'),
        'Mustard'    => __('Mustard'),
        'Celery'     => __('Celery'),
        'Lupin'      => __('Lupin'),
        'Molluscs'   => __('Molluscs'),
        'Sulphites'  => __('Sulphites'),
        'Other'      => __('Other'),
    ];

    // Severity options
    $severityOptions = [
        'Mild'     => __('Mild'),
        'Moderate' => __('Moderate'),
        'Severe'   => __('Severe'),
    ];

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $URL = $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_items_add.php';

        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $session->get('csrf_token')) {
            $URL .= '&return=error0';
            header("Location: {$URL}");
            exit;
        }

        $gibbonPersonID = $session->get('gibbonPersonID');

        // Validate required fields
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = $_POST['category'] ?? '';
        $isActive = $_POST['isActive'] ?? 'Y';

        if (empty($name) || empty($category)) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        // Handle photo upload
        $photoPath = null;
        if (!empty($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $fileUploader = $container->get(FileUploader::class);
            $fileUploader->setFileSuffixes(['jpg', 'jpeg', 'png', 'gif']);

            // Generate upload path
            $uploadPath = 'uploads/' . date('Y') . '/' . date('m');
            $absoluteUploadPath = $session->get('absolutePath') . '/' . $uploadPath;

            // Create directory if it doesn't exist
            if (!is_dir($absoluteUploadPath)) {
                mkdir($absoluteUploadPath, 0755, true);
            }

            // Upload the file
            $filename = $fileUploader->upload($_FILES['photo'], $uploadPath);

            if (!empty($filename)) {
                $photoPath = $uploadPath . '/' . $filename;
            }
        }

        // Prepare menu item data
        $menuItemData = [
            'name'        => $name,
            'description' => $description ?: null,
            'category'    => $category,
            'photoPath'   => $photoPath,
            'isActive'    => $isActive,
            'createdByID' => $gibbonPersonID,
        ];

        // Prepare nutritional data
        $nutritionData = null;
        $calories = $_POST['calories'] ?? '';
        $protein = $_POST['protein'] ?? '';
        $carbohydrates = $_POST['carbohydrates'] ?? '';
        $fat = $_POST['fat'] ?? '';
        $fiber = $_POST['fiber'] ?? '';
        $servingSize = trim($_POST['servingSize'] ?? '1 serving');

        if ($calories !== '' || $protein !== '' || $carbohydrates !== '' || $fat !== '' || $fiber !== '') {
            $nutritionData = [
                'servingSize'    => $servingSize,
                'calories'       => $calories !== '' ? floatval($calories) : null,
                'protein'        => $protein !== '' ? floatval($protein) : null,
                'carbohydrates'  => $carbohydrates !== '' ? floatval($carbohydrates) : null,
                'fat'            => $fat !== '' ? floatval($fat) : null,
                'fiber'          => $fiber !== '' ? floatval($fiber) : null,
            ];
        }

        // Insert menu item
        $menuItemID = $menuItemGateway->insertMenuItem($menuItemData, $nutritionData);

        if ($menuItemID === false) {
            // Delete uploaded file if database insert failed
            if ($photoPath) {
                @unlink($session->get('absolutePath') . '/' . $photoPath);
            }
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        }

        // Handle allergens
        $allergens = $_POST['allergens'] ?? [];
        $allergenSeverities = $_POST['allergenSeverity'] ?? [];

        if (!empty($allergens) && is_array($allergens)) {
            foreach ($allergens as $allergen) {
                $severity = $allergenSeverities[$allergen] ?? 'Moderate';
                $menuItemGateway->insertMenuItemAllergen($menuItemID, $allergen, $severity);
            }
        }

        // Success - redirect to menu items list
        $URL = $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_items.php&return=success0';
        header("Location: {$URL}");
        exit;
    }

    // Display return messages
    if (isset($_GET['return'])) {
        switch ($_GET['return']) {
            case 'success0':
                $page->addMessage(__('Menu item has been created successfully.'));
                break;
            case 'error0':
                $page->addError(__('Your request failed because you do not have access to this action.'));
                break;
            case 'error1':
                $page->addError(__('Your request failed because your inputs were invalid. Please enter a name and select a category.'));
                break;
            case 'error2':
                $page->addError(__('Your request failed due to a database error.'));
                break;
        }
    }

    // Create form
    $form = Form::create('addMenuItem', $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_items_add.php');
    $form->setTitle(__('Add Menu Item'));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('csrf_token', $session->get('csrf_token'));

    // Basic Information Section
    $form->addRow()->addHeading(__('Basic Information'));

    // Name
    $row = $form->addRow();
        $row->addLabel('name', __('Name'))
            ->description(__('The name of the menu item.'));
        $row->addTextField('name')
            ->required()
            ->maxLength(100);

    // Description
    $row = $form->addRow();
        $row->addLabel('description', __('Description'))
            ->description(__('A brief description of the menu item.'));
        $row->addTextArea('description')
            ->setRows(3)
            ->maxLength(1000);

    // Category
    $row = $form->addRow();
        $row->addLabel('category', __('Category'))
            ->description(__('The food category for this menu item.'));
        $row->addSelect('category')
            ->fromArray($categoryOptions)
            ->required();

    // Photo
    $row = $form->addRow();
        $row->addLabel('photo', __('Photo'))
            ->description(__('Optional photo of the menu item. Allowed types: jpg, jpeg, png, gif.'));
        $row->addFileUpload('photo')
            ->accepts('.jpg,.jpeg,.png,.gif');

    // Active status
    $row = $form->addRow();
        $row->addLabel('isActive', __('Active'))
            ->description(__('Whether this item is available for menu planning.'));
        $row->addYesNo('isActive')
            ->required()
            ->selected('Y');

    // Allergens Section
    $form->addRow()->addHeading(__('Allergens'));

    // Allergen warning
    echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">';
    echo '<p class="text-sm text-yellow-800">' . __('Select all allergens that this menu item contains. Each allergen can have a severity level.') . '</p>';
    echo '</div>';

    // Allergen checkboxes with severity
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">';
    foreach ($allergenOptions as $value => $label) {
        echo '<div class="bg-white border rounded-lg p-3">';
        echo '<label class="flex items-center mb-2">';
        echo '<input type="checkbox" name="allergens[]" value="' . htmlspecialchars($value) . '" class="allergenCheckbox mr-2" onchange="toggleSeverity(this)">';
        echo '<span class="font-medium">' . htmlspecialchars($label) . '</span>';
        echo '</label>';
        echo '<select name="allergenSeverity[' . htmlspecialchars($value) . ']" class="allergenSeverity w-full border rounded px-2 py-1 text-sm" disabled>';
        foreach ($severityOptions as $sevValue => $sevLabel) {
            $selected = $sevValue === 'Moderate' ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($sevValue) . '"' . $selected . '>' . htmlspecialchars($sevLabel) . '</option>';
        }
        echo '</select>';
        echo '</div>';
    }
    echo '</div>';

    // Nutritional Information Section
    $form->addRow()->addHeading(__('Nutritional Information'));

    // Info text
    echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">';
    echo '<p class="text-sm text-blue-800">' . __('Enter nutritional values per serving. Leave blank if not known.') . '</p>';
    echo '</div>';

    // Serving Size
    $row = $form->addRow();
        $row->addLabel('servingSize', __('Serving Size'))
            ->description(__('e.g., "1 cup", "100g", "1 piece"'));
        $row->addTextField('servingSize')
            ->maxLength(50)
            ->setValue('1 serving');

    // Calories
    $row = $form->addRow();
        $row->addLabel('calories', __('Calories'))
            ->description(__('Calories per serving'));
        $row->addNumber('calories')
            ->decimalPlaces(2)
            ->minimum(0)
            ->maximum(10000);

    // Macronutrients in a grid
    $row = $form->addRow();
        $row->addLabel('protein', __('Protein (g)'));
        $row->addNumber('protein')
            ->decimalPlaces(2)
            ->minimum(0)
            ->maximum(500);

    $row = $form->addRow();
        $row->addLabel('carbohydrates', __('Carbohydrates (g)'));
        $row->addNumber('carbohydrates')
            ->decimalPlaces(2)
            ->minimum(0)
            ->maximum(500);

    $row = $form->addRow();
        $row->addLabel('fat', __('Fat (g)'));
        $row->addNumber('fat')
            ->decimalPlaces(2)
            ->minimum(0)
            ->maximum(500);

    $row = $form->addRow();
        $row->addLabel('fiber', __('Fiber (g)'));
        $row->addNumber('fiber')
            ->decimalPlaces(2)
            ->minimum(0)
            ->maximum(100);

    // Submit button
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Add Menu Item'));

    echo $form->getOutput();

    // JavaScript for allergen severity toggle
    echo '<script>
    function toggleSeverity(checkbox) {
        var select = checkbox.parentElement.parentElement.querySelector(".allergenSeverity");
        if (checkbox.checked) {
            select.disabled = false;
        } else {
            select.disabled = true;
        }
    }
    </script>';

    // Back link
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/menu_items.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Menu Items') . '</a>';
    echo '</div>';
}
