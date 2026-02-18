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
use Gibbon\Forms\Prefab\DeleteForm;
use Gibbon\Module\ExampleModule\Domain\ExampleEntityGateway;

// Module setup - breadcrumbs
$page->breadcrumbs
    ->add(__('Example Module'), 'exampleModule.php')
    ->add(__('Manage Example Items'), 'exampleModule_manage.php')
    ->add(__('Delete'));

// Access check - CRITICAL: Address must be HARD-CODED (never use variables)
if (!isActionAccessible($guid, $connection2, '/modules/ExampleModule/exampleModule_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get ID parameter
    $gibbonExampleEntityID = $_GET['gibbonExampleEntityID'] ?? '';

    if (empty($gibbonExampleEntityID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
    } else {
        // Get gateway via DI container
        $exampleEntityGateway = $container->get(ExampleEntityGateway::class);

        // Get existing record
        $values = $exampleEntityGateway->selectExampleEntityByID($gibbonExampleEntityID);

        if (empty($values)) {
            $page->addError(__('The specified record cannot be found.'));
        } else {
            // Page header
            echo '<h2>' . __('Delete Example Item') . '</h2>';

            // Display item details
            echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">';
            echo '<p class="font-semibold">' . __('Are you sure you want to delete this item?') . '</p>';
            echo '<div class="mt-2">';
            echo '<p><strong>' . __('Title') . ':</strong> ' . htmlspecialchars($values['title']) . '</p>';
            if (!empty($values['description'])) {
                echo '<p><strong>' . __('Description') . ':</strong> ' . htmlspecialchars($values['description']) . '</p>';
            }
            echo '<p><strong>' . __('Status') . ':</strong> ' . __($values['status']) . '</p>';
            echo '</div>';
            echo '</div>';

            // Create delete form
            $form = DeleteForm::createForm($session->get('absoluteURL') . '/modules/ExampleModule/exampleModule_manage_deleteProcess.php?gibbonExampleEntityID=' . $gibbonExampleEntityID);
            echo $form->getOutput();
        }
    }
}
