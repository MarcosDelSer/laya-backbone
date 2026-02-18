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
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Module\ExampleModule\Domain\ExampleEntityGateway;

// Module setup - breadcrumbs
$page->breadcrumbs
    ->add(__('Example Module'), 'exampleModule.php')
    ->add(__('Manage Example Items'), 'exampleModule_manage.php')
    ->add(__('Edit'));

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
            echo '<h2>' . __('Edit Example Item') . '</h2>';

            // Create form
            $form = Form::create('editExampleItem', $session->get('absoluteURL') . '/modules/ExampleModule/exampleModule_manage_editProcess.php');
            $form->setFactory(DatabaseFormFactory::create($pdo));

            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('gibbonExampleEntityID', $gibbonExampleEntityID);

            // Title field
            $row = $form->addRow();
            $row->addLabel('title', __('Title'))->description(__('Required'));
            $row->addTextField('title')
                ->required()
                ->maxLength(100);

            // Description field
            $row = $form->addRow();
            $row->addLabel('description', __('Description'));
            $row->addTextArea('description')
                ->setRows(5)
                ->maxLength(1000);

            // Status field
            $row = $form->addRow();
            $row->addLabel('status', __('Status'));
            $row->addSelect('status')
                ->fromArray([
                    'Active' => __('Active'),
                    'Pending' => __('Pending'),
                    'Inactive' => __('Inactive'),
                ])
                ->required();

            // Person selector
            $row = $form->addRow();
            $row->addLabel('gibbonPersonID', __('Assign To Person'))->description(__('Select a person to assign this item to'));
            $row->addSelectUsers('gibbonPersonID')
                ->required();

            // Load existing data
            $form->loadAllValuesFrom($values);

            // Submit buttons
            $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

            echo $form->getOutput();
        }
    }
}
