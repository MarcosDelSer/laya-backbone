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
use Gibbon\Module\RBAC\Domain\RBACGateway;
use Gibbon\Module\RBAC\Domain\AuditGateway;

if (isActionAccessible($guid, $connection2, '/modules/RBAC/rbac_users.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Manage User Roles'), 'rbac_users.php')
        ->add(__('Assign Role'));

    // Get gateways
    $rbacGateway = $container->get(RBACGateway::class);
    $auditGateway = $container->get(AuditGateway::class);

    // Get current user info for audit logging
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $targetPersonID = $_POST['gibbonPersonID'] ?? '';
        $gibbonRBACRoleID = $_POST['gibbonRBACRoleID'] ?? '';
        $gibbonGroupID = !empty($_POST['gibbonGroupID']) ? $_POST['gibbonGroupID'] : null;
        $expiresAt = !empty($_POST['expiresAt']) ? Format::dateConvert($_POST['expiresAt']) . ' 23:59:59' : null;

        // Validate required fields
        if (empty($targetPersonID) || empty($gibbonRBACRoleID)) {
            $page->addError(__('Please select both a user and a role.'));
        } else {
            // Assign the role
            $result = $rbacGateway->assignRole(
                $targetPersonID,
                $gibbonRBACRoleID,
                $gibbonPersonID,
                $gibbonGroupID,
                $expiresAt
            );

            if ($result !== false) {
                // Log the action
                $role = $rbacGateway->getByID($gibbonRBACRoleID);
                $auditGateway->logRoleAssigned(
                    $gibbonPersonID,
                    'user',
                    $targetPersonID,
                    [
                        'roleID' => $gibbonRBACRoleID,
                        'roleName' => $role['name'] ?? '',
                        'groupID' => $gibbonGroupID,
                        'expiresAt' => $expiresAt,
                    ]
                );

                $page->addSuccess(__('Role has been assigned successfully.'));
            } else {
                $page->addError(__('Failed to assign role. Please try again.'));
            }
        }
    }

    // Get all active roles for dropdown
    $roles = $rbacGateway->selectAllRoles();
    $roleOptions = [];
    foreach ($roles as $role) {
        $roleOptions[$role['value']] = $role['name'];
    }

    // Get staff users for dropdown (querying gibbonPerson directly)
    $sql = "SELECT gibbonPersonID as value, CONCAT(surname, ', ', preferredName) as name
            FROM gibbonPerson
            WHERE status = 'Full'
            ORDER BY surname, preferredName";
    $result = $connection2->query($sql);
    $userOptions = [];
    while ($row = $result->fetch(\PDO::FETCH_ASSOC)) {
        $userOptions[$row['value']] = $row['name'];
    }

    // Get groups for dropdown (if groups table exists)
    $groupOptions = ['' => __('All Groups (No Restriction)')];
    try {
        $groupSql = "SELECT gibbonGroupID as value, name
                     FROM gibbonGroup
                     WHERE gibbonSchoolYearID = :gibbonSchoolYearID
                     ORDER BY name";
        $groupResult = $connection2->prepare($groupSql);
        $groupResult->execute(['gibbonSchoolYearID' => $session->get('gibbonSchoolYearID')]);
        while ($row = $groupResult->fetch(\PDO::FETCH_ASSOC)) {
            $groupOptions[$row['value']] = $row['name'];
        }
    } catch (\Exception $e) {
        // Groups table may not exist, continue without group restrictions
    }

    // Role assignment form
    $form = Form::create('assignRole', $session->get('absoluteURL') . '/index.php?q=/modules/RBAC/rbac_assign.php');
    $form->setTitle(__('Assign Role to User'));
    $form->setDescription(__('Assign an RBAC role to a user. You can optionally restrict access to specific groups.'));

    // User selection
    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __('User'));
        $row->addSelect('gibbonPersonID')
            ->fromArray($userOptions)
            ->required()
            ->placeholder(__('Select a user...'));

    // Role selection
    $row = $form->addRow();
        $row->addLabel('gibbonRBACRoleID', __('Role'));
        $row->addSelect('gibbonRBACRoleID')
            ->fromArray($roleOptions)
            ->required()
            ->placeholder(__('Select a role...'));

    // Group restriction (optional)
    if (count($groupOptions) > 1) {
        $row = $form->addRow();
            $row->addLabel('gibbonGroupID', __('Group Restriction'))
                ->description(__('Optionally limit this role to a specific group. Leave empty for access to all groups.'));
            $row->addSelect('gibbonGroupID')
                ->fromArray($groupOptions);
    }

    // Expiration date (optional)
    $row = $form->addRow();
        $row->addLabel('expiresAt', __('Expires On'))
            ->description(__('Optionally set an expiration date for this role assignment. Leave empty for permanent assignment.'));
        $row->addDate('expiresAt');

    // Role information section
    $form->addRow()->addHeading(__('Role Information'));

    $roleInfo = '<div id="roleInfo" class="hidden bg-gray-50 rounded p-4 mb-4">';
    $roleInfo .= '<div id="roleDescription" class="text-gray-600 mb-2"></div>';
    $roleInfo .= '<div id="rolePermissions" class="text-sm"></div>';
    $roleInfo .= '</div>';
    $form->addRow()->addContent($roleInfo);

    // Submit
    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Assign Role'));

    echo $form->getOutput();

    // JavaScript for role info display
    echo '<script>
    document.getElementById("gibbonRBACRoleID").addEventListener("change", function() {
        var roleID = this.value;
        var infoDiv = document.getElementById("roleInfo");

        if (!roleID) {
            infoDiv.classList.add("hidden");
            return;
        }

        // Role descriptions (could be loaded via AJAX for more detail)
        var roleDescriptions = {
            "director": "' . __('Full access to all data and settings. Can manage roles and permissions.') . '",
            "teacher": "' . __('Access to children and data within assigned groups.') . '",
            "assistant": "' . __('Limited access to children within assigned groups.') . '",
            "staff": "' . __('Access to own schedule and limited shared resources.') . '",
            "parent": "' . __('Access restricted to own children only.') . '"
        };

        var selectedText = this.options[this.selectedIndex].text;
        var roleType = selectedText.toLowerCase();

        document.getElementById("roleDescription").innerHTML = roleDescriptions[roleType] || "' . __('Custom role with specific permissions.') . '";
        infoDiv.classList.remove("hidden");
    });
    </script>';

    // Back link
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/RBAC/rbac_users.php" class="text-blue-600 hover:underline">';
    echo '&larr; ' . __('Back to User Roles');
    echo '</a>';
    echo '</div>';

    // Permission Matrix Preview
    echo '<div class="mt-6 bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Permission Matrix') . '</h3>';
    echo '<p class="text-sm text-gray-600 mb-4">' . __('Overview of permissions by role type:') . '</p>';

    $matrix = $rbacGateway->getPermissionMatrix();

    echo '<div class="overflow-x-auto">';
    echo '<table class="min-w-full divide-y divide-gray-200">';
    echo '<thead class="bg-gray-50">';
    echo '<tr>';
    echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">' . __('Role') . '</th>';
    echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">' . __('Permissions') . '</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody class="bg-white divide-y divide-gray-200">';

    foreach ($matrix as $roleID => $roleData) {
        echo '<tr>';
        echo '<td class="px-4 py-3 whitespace-nowrap">';
        echo '<span class="font-medium">' . htmlspecialchars($roleData['displayName']) . '</span>';
        echo '</td>';
        echo '<td class="px-4 py-3">';

        if (empty($roleData['permissions'])) {
            echo '<span class="text-gray-400">' . __('No permissions defined') . '</span>';
        } else {
            $permGroups = [];
            foreach ($roleData['permissions'] as $perm) {
                $resource = $perm['resource'];
                if (!isset($permGroups[$resource])) {
                    $permGroups[$resource] = [];
                }
                $permGroups[$resource][] = $perm['action'] . ' (' . $perm['scope'] . ')';
            }

            echo '<div class="flex flex-wrap gap-2">';
            foreach ($permGroups as $resource => $actions) {
                echo '<span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">';
                echo htmlspecialchars($resource) . ': ' . implode(', ', $actions);
                echo '</span>';
            }
            echo '</div>';
        }

        echo '</td>';
        echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</div>';
    echo '</div>';
}
