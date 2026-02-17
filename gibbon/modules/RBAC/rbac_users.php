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

if (isActionAccessible($guid, $connection2, '/modules/RBAC/rbac_users.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Manage User Roles'));

    // Get gateway
    $rbacGateway = $container->get(RBACGateway::class);

    // Filter options
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

    // Get all roles for filter dropdown
    $allRoles = $rbacGateway->selectAllRoles();
    $roleOptions = ['' => __('All Roles')];
    foreach ($allRoles as $role) {
        $roleOptions[$role['value']] = $role['name'];
    }

    $roleType = $_GET['roleType'] ?? '';
    $role = $_GET['role'] ?? '';
    $active = $_GET['active'] ?? 'Y';
    $search = $_GET['search'] ?? '';

    // Filter form
    $form = Form::create('filter', $session->get('absoluteURL') . '/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/RBAC/rbac_users.php');

    $row = $form->addRow();
        $row->addLabel('search', __('Search'));
        $row->addTextField('search')
            ->setValue($search)
            ->placeholder(__('Name or username...'))
            ->maxLength(50);

    $row = $form->addRow();
        $row->addLabel('role', __('Role'));
        $row->addSelect('role')
            ->fromArray($roleOptions)
            ->selected($role);

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
        ->sortBy(['surname', 'preferredName'], 'ASC')
        ->filterBy('role', $role)
        ->filterBy('roleType', $roleType)
        ->filterBy('active', $active)
        ->fromPOST();

    // Get user roles
    $userRoles = $rbacGateway->queryUserRoles($criteria);

    // Create data table
    $table = DataTable::createPaginated('userRoles', $criteria);
    $table->setTitle(__('User Role Assignments'));

    // Add header action
    $table->addHeaderAction('add', __('Assign Role'))
        ->setURL('/modules/RBAC/rbac_assign.php')
        ->displayLabel();

    // Add columns
    $table->addColumn('photo', __('Photo'))
        ->notSortable()
        ->width('5%')
        ->format(function ($row) use ($session) {
            $image = !empty($row['image_240']) ? $row['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            return '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-10 h-10 rounded-full object-cover" alt="">';
        });

    $table->addColumn('name', __('Name'))
        ->sortable(['surname', 'preferredName'])
        ->format(function ($row) {
            return Format::name('', $row['preferredName'], $row['surname'], 'Staff', true, true);
        });

    $table->addColumn('roleDisplayName', __('Role'))
        ->sortable(['roleDisplayName'])
        ->format(function ($row) {
            $typeColors = [
                'director'  => 'bg-purple-100 text-purple-800',
                'teacher'   => 'bg-blue-100 text-blue-800',
                'assistant' => 'bg-green-100 text-green-800',
                'staff'     => 'bg-yellow-100 text-yellow-800',
                'parent'    => 'bg-orange-100 text-orange-800',
            ];
            $color = $typeColors[$row['roleType']] ?? 'bg-gray-100 text-gray-800';
            return '<span class="' . $color . ' text-xs px-2 py-1 rounded font-medium">' . htmlspecialchars($row['roleDisplayName']) . '</span>';
        });

    $table->addColumn('roleType', __('Type'))
        ->format(function ($row) {
            return ucfirst($row['roleType']);
        });

    $table->addColumn('gibbonGroupID', __('Group'))
        ->notSortable()
        ->format(function ($row) {
            if (empty($row['gibbonGroupID'])) {
                return '<span class="text-gray-400">' . __('All Groups') . '</span>';
            }
            return __('Group') . ' #' . $row['gibbonGroupID'];
        });

    $table->addColumn('expiresAt', __('Expires'))
        ->format(function ($row) {
            if (empty($row['expiresAt'])) {
                return '<span class="text-gray-400">' . __('Never') . '</span>';
            }
            $expires = strtotime($row['expiresAt']);
            $now = time();
            if ($expires < $now) {
                return '<span class="text-red-600">' . Format::dateTime($row['expiresAt']) . ' (' . __('Expired') . ')</span>';
            }
            return Format::dateTime($row['expiresAt']);
        });

    $table->addColumn('active', __('Status'))
        ->format(function ($row) {
            if ($row['active'] === 'Y') {
                return '<span class="tag success">' . __('Active') . '</span>';
            }
            return '<span class="tag dull">' . __('Inactive') . '</span>';
        });

    $table->addColumn('assignedBy', __('Assigned By'))
        ->format(function ($row) {
            if (empty($row['assignedByName'])) {
                return '<span class="text-gray-400">-</span>';
            }
            return Format::name('', $row['assignedByName'], $row['assignedBySurname'], 'Staff');
        });

    $table->addColumn('timestampCreated', __('Assigned'))
        ->format(function ($row) {
            return Format::dateTime($row['timestampCreated']);
        });

    // Actions
    $table->addActionColumn()
        ->addParam('gibbonRBACUserRoleID')
        ->format(function ($row, $actions) {
            // View user's permissions
            $actions->addAction('view', __('View Permissions'))
                ->setURL('/modules/RBAC/rbac_user_permissions.php')
                ->addParam('gibbonPersonID', $row['gibbonPersonID'])
                ->setIcon('page_white');

            // Edit assignment
            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/RBAC/rbac_assign_edit.php')
                ->setIcon('config');

            // Revoke role
            $actions->addAction('delete', __('Revoke'))
                ->setURL('/modules/RBAC/rbac_revoke.php')
                ->setIcon('garbage')
                ->directLink()
                ->addConfirmation(__('Are you sure you wish to revoke this role from this user?'));
        });

    echo $table->render($userRoles);

    // Quick stats
    echo '<div class="mt-6 bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Assignment Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">';

    // Get count for each role type
    $roleTypes = ['director', 'teacher', 'assistant', 'staff', 'parent'];
    foreach ($roleTypes as $type) {
        $typeCriteria = $rbacGateway->newQueryCriteria(true)->filterBy('roleType', $type)->filterBy('active', 'Y');
        $typeUsers = $rbacGateway->queryUserRoles($typeCriteria);
        $count = $typeUsers->count();

        $typeColors = [
            'director'  => 'purple',
            'teacher'   => 'blue',
            'assistant' => 'green',
            'staff'     => 'yellow',
            'parent'    => 'orange',
        ];
        $color = $typeColors[$type] ?? 'gray';

        echo '<div class="bg-' . $color . '-50 rounded p-3">';
        echo '<span class="block text-sm font-medium text-' . $color . '-800">' . ucfirst($type) . 's</span>';
        echo '<span class="block text-2xl font-bold text-' . $color . '-600">' . $count . '</span>';
        echo '<span class="text-xs text-' . $color . '-500">' . __('active') . '</span>';
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';

    // Help note
    echo '<div class="message mt-4">';
    echo __('Users can have multiple roles. Group restrictions limit access to specific groups only. Directors always have access to all data.');
    echo '</div>';
}
