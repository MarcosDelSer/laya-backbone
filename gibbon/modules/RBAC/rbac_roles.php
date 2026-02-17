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
use Gibbon\Module\RBAC\Domain\RBACGateway;

if (isActionAccessible($guid, $connection2, '/modules/RBAC/rbac_roles.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Manage Roles'));

    // Get gateway
    $rbacGateway = $container->get(RBACGateway::class);

    // Filter form
    $roleTypeOptions = [
        ''          => __('All Role Types'),
        'director'  => __('Director'),
        'teacher'   => __('Teacher'),
        'assistant' => __('Assistant'),
        'staff'     => __('Staff'),
        'parent'    => __('Parent'),
    ];

    $activeOptions = [
        ''  => __('All Status'),
        'Y' => __('Active'),
        'N' => __('Inactive'),
    ];

    $roleType = $_GET['roleType'] ?? '';
    $active = $_GET['active'] ?? '';

    $form = Form::create('filter', $session->get('absoluteURL') . '/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/RBAC/rbac_roles.php');

    $row = $form->addRow();
        $row->addLabel('roleType', __('Role Type'));
        $row->addSelect('roleType')
            ->fromArray($roleTypeOptions)
            ->selected($roleType);

    $row = $form->addRow();
        $row->addLabel('active', __('Status'));
        $row->addSelect('active')
            ->fromArray($activeOptions)
            ->selected($active);

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    // Build query criteria
    $criteria = $rbacGateway->newQueryCriteria(true)
        ->sortBy(['sortOrder'], 'ASC')
        ->filterBy('roleType', $roleType)
        ->filterBy('active', $active)
        ->fromPOST();

    // Get roles
    $roles = $rbacGateway->queryRoles($criteria);

    // Create data table
    $table = DataTable::createPaginated('roles', $criteria);
    $table->setTitle(__('RBAC Roles'));

    // Add columns
    $table->addColumn('sortOrder', __('Order'))
        ->width('5%')
        ->format(function ($role) {
            return $role['sortOrder'];
        });

    $table->addColumn('displayName', __('Role Name'))
        ->sortable(['displayName'])
        ->format(function ($role) {
            $output = '<strong>' . htmlspecialchars($role['displayName']) . '</strong>';
            if ($role['isSystemRole'] === 'Y') {
                $output .= ' <span class="tag success text-xs ml-2">' . __('System') . '</span>';
            }
            return $output;
        });

    $table->addColumn('name', __('Internal Name'))
        ->format(function ($role) {
            return '<code class="text-xs">' . htmlspecialchars($role['name']) . '</code>';
        });

    $table->addColumn('roleType', __('Type'))
        ->sortable()
        ->format(function ($role) {
            $typeColors = [
                'director'  => 'bg-purple-100 text-purple-800',
                'teacher'   => 'bg-blue-100 text-blue-800',
                'assistant' => 'bg-green-100 text-green-800',
                'staff'     => 'bg-yellow-100 text-yellow-800',
                'parent'    => 'bg-orange-100 text-orange-800',
            ];
            $color = $typeColors[$role['roleType']] ?? 'bg-gray-100 text-gray-800';
            return '<span class="' . $color . ' text-xs px-2 py-1 rounded">' . ucfirst($role['roleType']) . '</span>';
        });

    $table->addColumn('description', __('Description'))
        ->format(function ($role) {
            if (empty($role['description'])) {
                return '<span class="text-gray-400">-</span>';
            }
            $desc = htmlspecialchars($role['description']);
            if (strlen($desc) > 60) {
                return '<span title="' . $desc . '">' . substr($desc, 0, 60) . '...</span>';
            }
            return $desc;
        });

    $table->addColumn('active', __('Status'))
        ->format(function ($role) {
            if ($role['active'] === 'Y') {
                return '<span class="tag success">' . __('Active') . '</span>';
            }
            return '<span class="tag dull">' . __('Inactive') . '</span>';
        });

    $table->addColumn('timestampCreated', __('Created'))
        ->format(function ($role) {
            return Format::dateTime($role['timestampCreated']);
        });

    // Actions
    $table->addActionColumn()
        ->addParam('gibbonRBACRoleID')
        ->format(function ($role, $actions) {
            // View permissions for this role
            $actions->addAction('view', __('View Permissions'))
                ->setURL('/modules/RBAC/rbac_permissions.php')
                ->setIcon('page_white');

            // Edit role (only for non-system roles)
            if ($role['isSystemRole'] === 'N') {
                $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/RBAC/rbac_roles_edit.php')
                    ->setIcon('config');

                $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/RBAC/rbac_roles_deleteProcess.php')
                    ->setIcon('garbage')
                    ->directLink()
                    ->addConfirmation(__('Are you sure you wish to delete this role? Users assigned to this role will lose their access.'));
            }
        });

    echo $table->render($roles);

    // Summary section
    echo '<div class="mt-6 bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Role Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">';

    // Get count for each role type
    $roleTypes = ['director', 'teacher', 'assistant', 'staff', 'parent'];
    foreach ($roleTypes as $type) {
        $typeCriteria = $rbacGateway->newQueryCriteria(true)->filterBy('roleType', $type)->filterBy('active', 'Y');
        $typeRoles = $rbacGateway->queryRoles($typeCriteria);
        $count = $typeRoles->count();

        $typeColors = [
            'director'  => 'purple',
            'teacher'   => 'blue',
            'assistant' => 'green',
            'staff'     => 'yellow',
            'parent'    => 'orange',
        ];
        $color = $typeColors[$type] ?? 'gray';

        echo '<div class="bg-' . $color . '-50 rounded p-3">';
        echo '<span class="block text-sm font-medium text-' . $color . '-800">' . ucfirst($type) . '</span>';
        echo '<span class="block text-2xl font-bold text-' . $color . '-600">' . $count . '</span>';
        echo '<span class="text-xs text-' . $color . '-500">' . __('role(s)') . '</span>';
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';

    // Information note
    echo '<div class="message mt-4">';
    echo __('System roles (Director, Teacher, Assistant, Staff, Parent) are protected and cannot be deleted. You can only modify their permissions.');
    echo '</div>';
}
