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
    ->add(__('View Example Items'));

// Access check - CRITICAL: Address must be HARD-CODED (never use variables)
if (!isActionAccessible($guid, $connection2, '/modules/ExampleModule/exampleModule_view.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get gateway via DI container
    $exampleEntityGateway = $container->get(ExampleEntityGateway::class);

    // Page header
    echo '<h2>' . __('View Example Items') . '</h2>';
    echo '<p>' . __('This is a read-only view of all example items.') . '</p>';

    // Filter form
    $status = $_GET['status'] ?? '';

    $form = Form::create('filterForm', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/ExampleModule/exampleModule_view.php');

    $row = $form->addRow();
    $row->addLabel('status', __('Filter by Status'));
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
    $table = DataTable::createPaginated('viewItems', $criteria);

    $table->addColumn('title', __('Title'))
        ->sortable();

    $table->addColumn('description', __('Description'))
        ->format(function ($row) {
            if (empty($row['description'])) {
                return '<i class="text-gray-400">' . __('No description') . '</i>';
            }
            return nl2br(htmlspecialchars($row['description']));
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

    $table->addColumn('timestampModified', __('Last Modified'))
        ->sortable()
        ->format(function ($row) {
            return Format::relativeTime($row['timestampModified']);
        });

    echo $table->render($items);
}
