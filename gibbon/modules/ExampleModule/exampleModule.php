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
$page->breadcrumbs->add(__('Example Module'));

// Access check - CRITICAL: Address must be HARD-CODED (never use variables)
if (!isActionAccessible($guid, $connection2, '/modules/ExampleModule/exampleModule.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateway via DI container
    $exampleEntityGateway = $container->get(ExampleEntityGateway::class);

    // Page header
    echo '<h2>' . __('Example Module Dashboard') . '</h2>';

    // Get statistics
    $stats = $exampleEntityGateway->getStatistics($gibbonSchoolYearID);

    // Display summary statistics
    echo '<div class="bg-white rounded-lg shadow p-4 mb-6">';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">';
    echo '<div><span class="block text-3xl font-bold text-blue-600">' . ($stats['total'] ?? 0) . '</span><span class="text-sm text-gray-600">' . __('Total Items') . '</span></div>';
    echo '<div><span class="block text-3xl font-bold text-green-600">' . ($stats['active'] ?? 0) . '</span><span class="text-sm text-gray-600">' . __('Active') . '</span></div>';
    echo '<div><span class="block text-3xl font-bold text-orange-500">' . ($stats['pending'] ?? 0) . '</span><span class="text-sm text-gray-600">' . __('Pending') . '</span></div>';
    echo '<div><span class="block text-3xl font-bold text-gray-500">' . ($stats['inactive'] ?? 0) . '</span><span class="text-sm text-gray-600">' . __('Inactive') . '</span></div>';
    echo '</div>';
    echo '</div>';

    // Welcome message
    echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-2">' . __('Welcome to Example Module') . '</h3>';
    echo '<p class="text-sm text-gray-700">' . __('This is a template module demonstrating Gibbon module development patterns. Use the menu to manage items, view data, or configure settings.') . '</p>';
    echo '</div>';

    // Recent items section
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Recent Items') . '</h3>';

    // Query recent items
    $criteria = $exampleEntityGateway->newQueryCriteria(true)
        ->sortBy('timestampCreated', 'DESC')
        ->pageSize(10);

    $recentItems = $exampleEntityGateway->queryExampleEntities($criteria, $gibbonSchoolYearID);

    // Render data table
    $table = DataTable::createPaginated('recentItems', $criteria);

    $table->addColumn('title', __('Title'));

    $table->addColumn('status', __('Status'))
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
        ->format(function ($row) {
            return Format::relativeTime($row['timestampCreated']);
        });

    echo $table->render($recentItems);

    // Quick actions
    echo '<div class="mt-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Quick Actions') . '</h3>';
    echo '<div class="flex gap-3">';

    // Check if user has manage permission
    if (isActionAccessible($guid, $connection2, '/modules/ExampleModule/exampleModule_manage.php')) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ExampleModule/exampleModule_manage_add.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Add New Item') . '</a>';
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ExampleModule/exampleModule_manage.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">' . __('Manage Items') . '</a>';
    }

    // View items link (available to all with access)
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ExampleModule/exampleModule_view.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('View All Items') . '</a>';

    echo '</div>';
    echo '</div>';
}
