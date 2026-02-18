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
use Gibbon\Module\ExampleModule\Domain\ExampleEntityGateway;

// Module setup - breadcrumbs
$page->breadcrumbs
    ->add(__('Example Module'), 'exampleModule.php')
    ->add(__('Manage Example Items'));

// Access check - CRITICAL: Address must be HARD-CODED (never use variables)
if (!isActionAccessible($guid, $connection2, '/modules/ExampleModule/exampleModule_manage.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get gateway via DI container
    $exampleEntityGateway = $container->get(ExampleEntityGateway::class);

    // Page header
    echo '<h2>' . __('Manage Example Items') . '</h2>';

    // Filter form
    $status = $_GET['status'] ?? '';

    $form = Form::create('filterForm', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/ExampleModule/exampleModule_manage.php');

    $row = $form->addRow();
    $row->addLabel('status', __('Status'));
    $row->addSelect('status')
        ->fromArray([
            '' => __('All'),
            'Active' => __('Active'),
            'Pending' => __('Pending'),
            'Inactive' => __('Inactive'),
        ])
        ->selected($status);

    $row = $form->addRow();
    $row->addSubmit(__('Filter'));

    echo $form->getOutput();

    // Query items with filter
    $criteria = $exampleEntityGateway->newQueryCriteria(true)
        ->sortBy('timestampCreated', 'DESC')
        ->pageSize(50)
        ->fromPOST();

    if (!empty($status)) {
        $items = $exampleEntityGateway->queryExampleEntitiesByStatus($criteria, $gibbonSchoolYearID, $status);
    } else {
        $items = $exampleEntityGateway->queryExampleEntities($criteria, $gibbonSchoolYearID);
    }

    // Render data table
    $table = DataTable::createPaginated('manageItems', $criteria);

    $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/ExampleModule/exampleModule_manage_add.php')
        ->displayLabel();

    $table->addColumn('title', __('Title'))
        ->sortable();

    $table->addColumn('description', __('Description'))
        ->format(function ($row) {
            return !empty($row['description']) ? Format::truncate(htmlspecialchars($row['description']), 100) : '<i>' . __('No description') . '</i>';
        });

    $table->addColumn('status', __('Status'))
        ->sortable()
        ->format(function ($row) {
            $statusColors = [
                'Active' => 'bg-green-100 text-green-800',
                'Pending' => 'bg-yellow-100 text-yellow-800',
                'Inactive' => 'bg-gray-100 text-gray-800',
            ];
            $colorClass = $statusColors[$row['status']] ?? 'bg-gray-100 text-gray-800';
            return '<span class="' . $colorClass . ' text-xs px-2 py-1 rounded">' . __($row['status']) . '</span>';
        });

    $table->addColumn('createdBy', __('Created By'))
        ->format(function ($row) {
            return Format::name($row['createdTitle'], $row['createdPreferredName'], $row['createdSurname'], 'Staff', false, true);
        });

    $table->addColumn('timestampCreated', __('Created'))
        ->sortable()
        ->format(function ($row) {
            return Format::date($row['timestampCreated']) . '<br/><small class="text-gray-500">' . Format::timeReadable($row['timestampCreated']) . '</small>';
        });

    // Action buttons
    $table->addActionColumn()
        ->addParam('gibbonExampleEntityID')
        ->format(function ($row, $actions) {
            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/ExampleModule/exampleModule_manage_edit.php');

            $actions->addAction('delete', __('Delete'))
                ->setURL('/modules/ExampleModule/exampleModule_manage_delete.php');
        });

    echo $table->render($items);
}
