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

namespace Gibbon\Module\RBAC\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * RBAC Gateway
 *
 * Handles role-based access control database operations including roles,
 * permissions, and user-role assignments.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class RBACGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonRBACRole';
    private static $primaryKey = 'gibbonRBACRoleID';

    private static $searchableColumns = ['gibbonRBACRole.name', 'gibbonRBACRole.displayName', 'gibbonRBACRole.description'];

    /**
     * Query roles with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryRoles(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonRBACRole.gibbonRBACRoleID',
                'gibbonRBACRole.name',
                'gibbonRBACRole.displayName',
                'gibbonRBACRole.description',
                'gibbonRBACRole.roleType',
                'gibbonRBACRole.isSystemRole',
                'gibbonRBACRole.active',
                'gibbonRBACRole.sortOrder',
                'gibbonRBACRole.timestampCreated',
                'gibbonRBACRole.timestampModified',
            ])
            ->orderBy(['gibbonRBACRole.sortOrder ASC']);

        $criteria->addFilterRules([
            'roleType' => function ($query, $roleType) {
                return $query
                    ->where('gibbonRBACRole.roleType=:roleType')
                    ->bindValue('roleType', $roleType);
            },
            'active' => function ($query, $value) {
                return $query
                    ->where('gibbonRBACRole.active=:active')
                    ->bindValue('active', $value);
            },
            'isSystemRole' => function ($query, $value) {
                return $query
                    ->where('gibbonRBACRole.isSystemRole=:isSystemRole')
                    ->bindValue('isSystemRole', $value);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query active roles only.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryActiveRoles(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonRBACRole.gibbonRBACRoleID',
                'gibbonRBACRole.name',
                'gibbonRBACRole.displayName',
                'gibbonRBACRole.description',
                'gibbonRBACRole.roleType',
                'gibbonRBACRole.isSystemRole',
                'gibbonRBACRole.sortOrder',
            ])
            ->where('gibbonRBACRole.active=:active')
            ->bindValue('active', 'Y')
            ->orderBy(['gibbonRBACRole.sortOrder ASC']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get role by name.
     *
     * @param string $name
     * @return array
     */
    public function getRoleByName($name)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('name=:name')
            ->bindValue('name', $name);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : [];
    }

    /**
     * Get role by type.
     *
     * @param string $roleType
     * @return array
     */
    public function getRoleByType($roleType)
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['*'])
            ->where('roleType=:roleType')
            ->bindValue('roleType', $roleType)
            ->where('active=:active')
            ->bindValue('active', 'Y')
            ->orderBy(['sortOrder ASC'])
            ->limit(1);

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : [];
    }

    /**
     * Select all roles for dropdown.
     *
     * @return \Gibbon\Database\Result
     */
    public function selectAllRoles()
    {
        $query = $this
            ->newSelect()
            ->from($this->getTableName())
            ->cols(['gibbonRBACRoleID as value', 'displayName as name'])
            ->where('active=:active')
            ->bindValue('active', 'Y')
            ->orderBy(['sortOrder ASC']);

        return $this->runSelect($query);
    }

    /**
     * Query permissions with criteria support.
     *
     * @param QueryCriteria $criteria
     * @param int|null $gibbonRBACRoleID
     * @return DataSet
     */
    public function queryPermissions(QueryCriteria $criteria, $gibbonRBACRoleID = null)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonRBACPermission')
            ->cols([
                'gibbonRBACPermission.gibbonRBACPermissionID',
                'gibbonRBACPermission.gibbonRBACRoleID',
                'gibbonRBACPermission.resource',
                'gibbonRBACPermission.action',
                'gibbonRBACPermission.scope',
                'gibbonRBACPermission.active',
                'gibbonRBACPermission.timestampCreated',
                'gibbonRBACRole.name as roleName',
                'gibbonRBACRole.displayName as roleDisplayName',
            ])
            ->innerJoin('gibbonRBACRole', 'gibbonRBACPermission.gibbonRBACRoleID=gibbonRBACRole.gibbonRBACRoleID');

        if ($gibbonRBACRoleID !== null) {
            $query->where('gibbonRBACPermission.gibbonRBACRoleID=:gibbonRBACRoleID')
                  ->bindValue('gibbonRBACRoleID', $gibbonRBACRoleID);
        }

        $criteria->addFilterRules([
            'role' => function ($query, $roleID) {
                return $query
                    ->where('gibbonRBACPermission.gibbonRBACRoleID=:roleID')
                    ->bindValue('roleID', $roleID);
            },
            'resource' => function ($query, $resource) {
                return $query
                    ->where('gibbonRBACPermission.resource=:resource')
                    ->bindValue('resource', $resource);
            },
            'action' => function ($query, $action) {
                return $query
                    ->where('gibbonRBACPermission.action=:action')
                    ->bindValue('action', $action);
            },
            'active' => function ($query, $value) {
                return $query
                    ->where('gibbonRBACPermission.active=:active')
                    ->bindValue('active', $value);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select permissions for a role.
     *
     * @param int $gibbonRBACRoleID
     * @return \Gibbon\Database\Result
     */
    public function selectPermissionsByRole($gibbonRBACRoleID)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonRBACPermission')
            ->cols(['*'])
            ->where('gibbonRBACRoleID=:gibbonRBACRoleID')
            ->bindValue('gibbonRBACRoleID', $gibbonRBACRoleID)
            ->where('active=:active')
            ->bindValue('active', 'Y');

        return $this->runSelect($query);
    }

    /**
     * Check if a role has a specific permission.
     *
     * @param int $gibbonRBACRoleID
     * @param string $resource
     * @param string $action
     * @return array|null
     */
    public function getPermission($gibbonRBACRoleID, $resource, $action)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonRBACPermission')
            ->cols(['*'])
            ->where('gibbonRBACRoleID=:gibbonRBACRoleID')
            ->bindValue('gibbonRBACRoleID', $gibbonRBACRoleID)
            ->where('resource=:resource')
            ->bindValue('resource', $resource)
            ->where('action=:action')
            ->bindValue('action', $action)
            ->where('active=:active')
            ->bindValue('active', 'Y');

        $result = $this->runSelect($query);
        return $result->isNotEmpty() ? $result->fetch() : null;
    }

    /**
     * Query user role assignments with criteria support.
     *
     * @param QueryCriteria $criteria
     * @return DataSet
     */
    public function queryUserRoles(QueryCriteria $criteria)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonRBACUserRole')
            ->cols([
                'gibbonRBACUserRole.gibbonRBACUserRoleID',
                'gibbonRBACUserRole.gibbonPersonID',
                'gibbonRBACUserRole.gibbonRBACRoleID',
                'gibbonRBACUserRole.gibbonGroupID',
                'gibbonRBACUserRole.assignedByID',
                'gibbonRBACUserRole.expiresAt',
                'gibbonRBACUserRole.active',
                'gibbonRBACUserRole.timestampCreated',
                'gibbonRBACUserRole.timestampModified',
                'gibbonRBACRole.name as roleName',
                'gibbonRBACRole.displayName as roleDisplayName',
                'gibbonRBACRole.roleType',
                'gibbonPerson.preferredName',
                'gibbonPerson.surname',
                'gibbonPerson.image_240',
                'assignedBy.preferredName as assignedByName',
                'assignedBy.surname as assignedBySurname',
            ])
            ->innerJoin('gibbonRBACRole', 'gibbonRBACUserRole.gibbonRBACRoleID=gibbonRBACRole.gibbonRBACRoleID')
            ->innerJoin('gibbonPerson', 'gibbonRBACUserRole.gibbonPersonID=gibbonPerson.gibbonPersonID')
            ->leftJoin('gibbonPerson as assignedBy', 'gibbonRBACUserRole.assignedByID=assignedBy.gibbonPersonID');

        $criteria->addFilterRules([
            'person' => function ($query, $gibbonPersonID) {
                return $query
                    ->where('gibbonRBACUserRole.gibbonPersonID=:gibbonPersonID')
                    ->bindValue('gibbonPersonID', $gibbonPersonID);
            },
            'role' => function ($query, $gibbonRBACRoleID) {
                return $query
                    ->where('gibbonRBACUserRole.gibbonRBACRoleID=:gibbonRBACRoleID')
                    ->bindValue('gibbonRBACRoleID', $gibbonRBACRoleID);
            },
            'group' => function ($query, $gibbonGroupID) {
                return $query
                    ->where('gibbonRBACUserRole.gibbonGroupID=:gibbonGroupID')
                    ->bindValue('gibbonGroupID', $gibbonGroupID);
            },
            'active' => function ($query, $value) {
                return $query
                    ->where('gibbonRBACUserRole.active=:active')
                    ->bindValue('active', $value);
            },
            'roleType' => function ($query, $roleType) {
                return $query
                    ->where('gibbonRBACRole.roleType=:roleType')
                    ->bindValue('roleType', $roleType);
            },
        ]);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Query user roles by person.
     *
     * @param QueryCriteria $criteria
     * @param int $gibbonPersonID
     * @return DataSet
     */
    public function queryUserRolesByPerson(QueryCriteria $criteria, $gibbonPersonID)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonRBACUserRole')
            ->cols([
                'gibbonRBACUserRole.gibbonRBACUserRoleID',
                'gibbonRBACUserRole.gibbonRBACRoleID',
                'gibbonRBACUserRole.gibbonGroupID',
                'gibbonRBACUserRole.expiresAt',
                'gibbonRBACUserRole.active',
                'gibbonRBACUserRole.timestampCreated',
                'gibbonRBACRole.name as roleName',
                'gibbonRBACRole.displayName as roleDisplayName',
                'gibbonRBACRole.roleType',
                'gibbonRBACRole.description as roleDescription',
            ])
            ->innerJoin('gibbonRBACRole', 'gibbonRBACUserRole.gibbonRBACRoleID=gibbonRBACRole.gibbonRBACRoleID')
            ->where('gibbonRBACUserRole.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonRBACUserRole.active=:active')
            ->bindValue('active', 'Y')
            ->where('(gibbonRBACUserRole.expiresAt IS NULL OR gibbonRBACUserRole.expiresAt > NOW())')
            ->orderBy(['gibbonRBACRole.sortOrder ASC']);

        return $this->runQuery($query, $criteria);
    }

    /**
     * Select all active role assignments for a person.
     *
     * @param int $gibbonPersonID
     * @return \Gibbon\Database\Result
     */
    public function selectUserRolesByPerson($gibbonPersonID)
    {
        $query = $this
            ->newSelect()
            ->from('gibbonRBACUserRole')
            ->cols([
                'gibbonRBACUserRole.*',
                'gibbonRBACRole.name as roleName',
                'gibbonRBACRole.displayName as roleDisplayName',
                'gibbonRBACRole.roleType',
            ])
            ->innerJoin('gibbonRBACRole', 'gibbonRBACUserRole.gibbonRBACRoleID=gibbonRBACRole.gibbonRBACRoleID')
            ->where('gibbonRBACUserRole.gibbonPersonID=:gibbonPersonID')
            ->bindValue('gibbonPersonID', $gibbonPersonID)
            ->where('gibbonRBACUserRole.active=:active')
            ->bindValue('active', 'Y')
            ->where('(gibbonRBACUserRole.expiresAt IS NULL OR gibbonRBACUserRole.expiresAt > NOW())')
            ->orderBy(['gibbonRBACRole.sortOrder ASC']);

        return $this->runSelect($query);
    }

    /**
     * Get all permissions for a person across all their roles.
     *
     * @param int $gibbonPersonID
     * @return \Gibbon\Database\Result
     */
    public function selectUserPermissions($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT DISTINCT
                    gibbonRBACPermission.resource,
                    gibbonRBACPermission.action,
                    gibbonRBACPermission.scope,
                    gibbonRBACRole.name as roleName,
                    gibbonRBACRole.roleType,
                    gibbonRBACUserRole.gibbonGroupID
                FROM gibbonRBACUserRole
                INNER JOIN gibbonRBACRole ON gibbonRBACUserRole.gibbonRBACRoleID=gibbonRBACRole.gibbonRBACRoleID
                INNER JOIN gibbonRBACPermission ON gibbonRBACRole.gibbonRBACRoleID=gibbonRBACPermission.gibbonRBACRoleID
                WHERE gibbonRBACUserRole.gibbonPersonID=:gibbonPersonID
                AND gibbonRBACUserRole.active='Y'
                AND gibbonRBACPermission.active='Y'
                AND (gibbonRBACUserRole.expiresAt IS NULL OR gibbonRBACUserRole.expiresAt > NOW())
                ORDER BY gibbonRBACPermission.resource, gibbonRBACPermission.action";

        return $this->db()->select($sql, $data);
    }

    /**
     * Check if a user has a specific permission.
     *
     * @param int $gibbonPersonID
     * @param string $resource
     * @param string $action
     * @return array|null Permission details if granted, null otherwise
     */
    public function checkUserPermission($gibbonPersonID, $resource, $action)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'resource' => $resource,
            'action' => $action,
        ];
        $sql = "SELECT
                    gibbonRBACPermission.gibbonRBACPermissionID,
                    gibbonRBACPermission.resource,
                    gibbonRBACPermission.action,
                    gibbonRBACPermission.scope,
                    gibbonRBACRole.name as roleName,
                    gibbonRBACRole.roleType,
                    gibbonRBACUserRole.gibbonGroupID
                FROM gibbonRBACUserRole
                INNER JOIN gibbonRBACRole ON gibbonRBACUserRole.gibbonRBACRoleID=gibbonRBACRole.gibbonRBACRoleID
                INNER JOIN gibbonRBACPermission ON gibbonRBACRole.gibbonRBACRoleID=gibbonRBACPermission.gibbonRBACRoleID
                WHERE gibbonRBACUserRole.gibbonPersonID=:gibbonPersonID
                AND gibbonRBACUserRole.active='Y'
                AND gibbonRBACPermission.active='Y'
                AND gibbonRBACPermission.resource=:resource
                AND (gibbonRBACPermission.action=:action OR gibbonRBACPermission.action='manage')
                AND (gibbonRBACUserRole.expiresAt IS NULL OR gibbonRBACUserRole.expiresAt > NOW())
                LIMIT 1";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Assign a role to a user.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonRBACRoleID
     * @param int $assignedByID
     * @param int|null $gibbonGroupID
     * @param string|null $expiresAt
     * @return int|false
     */
    public function assignRole($gibbonPersonID, $gibbonRBACRoleID, $assignedByID, $gibbonGroupID = null, $expiresAt = null)
    {
        // Check if assignment already exists
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonRBACRoleID' => $gibbonRBACRoleID,
            'gibbonGroupID' => $gibbonGroupID,
        ];

        $sql = "SELECT gibbonRBACUserRoleID FROM gibbonRBACUserRole
                WHERE gibbonPersonID=:gibbonPersonID
                AND gibbonRBACRoleID=:gibbonRBACRoleID
                AND (gibbonGroupID=:gibbonGroupID OR (gibbonGroupID IS NULL AND :gibbonGroupID IS NULL))";

        $existing = $this->db()->selectOne($sql, $data);

        if ($existing) {
            // Reactivate existing assignment
            $updateData = [
                'active' => 'Y',
                'assignedByID' => $assignedByID,
                'expiresAt' => $expiresAt,
            ];
            $this->db()->update('gibbonRBACUserRole', $updateData, ['gibbonRBACUserRoleID' => $existing['gibbonRBACUserRoleID']]);
            return $existing['gibbonRBACUserRoleID'];
        }

        // Create new assignment
        $insertData = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonRBACRoleID' => $gibbonRBACRoleID,
            'gibbonGroupID' => $gibbonGroupID,
            'assignedByID' => $assignedByID,
            'expiresAt' => $expiresAt,
            'active' => 'Y',
        ];

        return $this->db()->insert('gibbonRBACUserRole', $insertData);
    }

    /**
     * Revoke a role from a user.
     *
     * @param int $gibbonRBACUserRoleID
     * @return bool
     */
    public function revokeRole($gibbonRBACUserRoleID)
    {
        return $this->db()->update('gibbonRBACUserRole', ['active' => 'N'], ['gibbonRBACUserRoleID' => $gibbonRBACUserRoleID]);
    }

    /**
     * Revoke a specific role assignment by person and role.
     *
     * @param int $gibbonPersonID
     * @param int $gibbonRBACRoleID
     * @param int|null $gibbonGroupID
     * @return bool
     */
    public function revokeRoleByPerson($gibbonPersonID, $gibbonRBACRoleID, $gibbonGroupID = null)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonRBACRoleID' => $gibbonRBACRoleID,
        ];

        if ($gibbonGroupID !== null) {
            $sql = "UPDATE gibbonRBACUserRole SET active='N'
                    WHERE gibbonPersonID=:gibbonPersonID
                    AND gibbonRBACRoleID=:gibbonRBACRoleID
                    AND gibbonGroupID=:gibbonGroupID";
            $data['gibbonGroupID'] = $gibbonGroupID;
        } else {
            $sql = "UPDATE gibbonRBACUserRole SET active='N'
                    WHERE gibbonPersonID=:gibbonPersonID
                    AND gibbonRBACRoleID=:gibbonRBACRoleID
                    AND gibbonGroupID IS NULL";
        }

        return $this->db()->statement($sql, $data);
    }

    /**
     * Get accessible group IDs for a person (for group-level filtering).
     *
     * @param int $gibbonPersonID
     * @return array
     */
    public function getAccessibleGroupIDs($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT DISTINCT gibbonRBACUserRole.gibbonGroupID
                FROM gibbonRBACUserRole
                INNER JOIN gibbonRBACRole ON gibbonRBACUserRole.gibbonRBACRoleID=gibbonRBACRole.gibbonRBACRoleID
                WHERE gibbonRBACUserRole.gibbonPersonID=:gibbonPersonID
                AND gibbonRBACUserRole.active='Y'
                AND gibbonRBACUserRole.gibbonGroupID IS NOT NULL
                AND (gibbonRBACUserRole.expiresAt IS NULL OR gibbonRBACUserRole.expiresAt > NOW())";

        $result = $this->db()->select($sql, $data);
        return $result->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Check if user is a director (has all-scope access).
     *
     * @param int $gibbonPersonID
     * @return bool
     */
    public function isDirector($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT 1 FROM gibbonRBACUserRole
                INNER JOIN gibbonRBACRole ON gibbonRBACUserRole.gibbonRBACRoleID=gibbonRBACRole.gibbonRBACRoleID
                WHERE gibbonRBACUserRole.gibbonPersonID=:gibbonPersonID
                AND gibbonRBACRole.roleType='director'
                AND gibbonRBACUserRole.active='Y'
                AND (gibbonRBACUserRole.expiresAt IS NULL OR gibbonRBACUserRole.expiresAt > NOW())
                LIMIT 1";

        return (bool) $this->db()->selectOne($sql, $data);
    }

    /**
     * Get permission matrix for all roles and resources.
     *
     * @return array
     */
    public function getPermissionMatrix()
    {
        $data = [];
        $sql = "SELECT
                    gibbonRBACRole.gibbonRBACRoleID,
                    gibbonRBACRole.name as roleName,
                    gibbonRBACRole.displayName,
                    gibbonRBACPermission.resource,
                    gibbonRBACPermission.action,
                    gibbonRBACPermission.scope
                FROM gibbonRBACRole
                LEFT JOIN gibbonRBACPermission ON gibbonRBACRole.gibbonRBACRoleID=gibbonRBACPermission.gibbonRBACRoleID
                    AND gibbonRBACPermission.active='Y'
                WHERE gibbonRBACRole.active='Y'
                ORDER BY gibbonRBACRole.sortOrder, gibbonRBACPermission.resource, gibbonRBACPermission.action";

        $result = $this->db()->select($sql, $data);
        $matrix = [];

        while ($row = $result->fetch()) {
            $roleID = $row['gibbonRBACRoleID'];
            if (!isset($matrix[$roleID])) {
                $matrix[$roleID] = [
                    'roleName' => $row['roleName'],
                    'displayName' => $row['displayName'],
                    'permissions' => [],
                ];
            }
            if ($row['resource']) {
                $matrix[$roleID]['permissions'][] = [
                    'resource' => $row['resource'],
                    'action' => $row['action'],
                    'scope' => $row['scope'],
                ];
            }
        }

        return $matrix;
    }

    /**
     * Add a permission to a role.
     *
     * @param int $gibbonRBACRoleID
     * @param string $resource
     * @param string $action
     * @param string $scope
     * @return int|false
     */
    public function addPermission($gibbonRBACRoleID, $resource, $action, $scope = 'own')
    {
        $data = [
            'gibbonRBACRoleID' => $gibbonRBACRoleID,
            'resource' => $resource,
            'action' => $action,
            'scope' => $scope,
            'active' => 'Y',
        ];

        return $this->db()->insert('gibbonRBACPermission', $data);
    }

    /**
     * Remove a permission from a role.
     *
     * @param int $gibbonRBACPermissionID
     * @return bool
     */
    public function removePermission($gibbonRBACPermissionID)
    {
        return $this->db()->update('gibbonRBACPermission', ['active' => 'N'], ['gibbonRBACPermissionID' => $gibbonRBACPermissionID]);
    }

    /**
     * Get users by role type.
     *
     * @param string $roleType
     * @return \Gibbon\Database\Result
     */
    public function selectUsersByRoleType($roleType)
    {
        $data = ['roleType' => $roleType];
        $sql = "SELECT DISTINCT
                    gibbonPerson.gibbonPersonID,
                    gibbonPerson.preferredName,
                    gibbonPerson.surname,
                    gibbonPerson.email
                FROM gibbonRBACUserRole
                INNER JOIN gibbonRBACRole ON gibbonRBACUserRole.gibbonRBACRoleID=gibbonRBACRole.gibbonRBACRoleID
                INNER JOIN gibbonPerson ON gibbonRBACUserRole.gibbonPersonID=gibbonPerson.gibbonPersonID
                WHERE gibbonRBACRole.roleType=:roleType
                AND gibbonRBACUserRole.active='Y'
                AND gibbonPerson.status='Full'
                AND (gibbonRBACUserRole.expiresAt IS NULL OR gibbonRBACUserRole.expiresAt > NOW())
                ORDER BY gibbonPerson.surname, gibbonPerson.preferredName";

        return $this->db()->select($sql, $data);
    }
}
