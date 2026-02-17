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

// This file describes the module, including database tables

// Basic variables
$name        = 'RBAC';
$description = 'Role-Based Access Control management: 5-role system with group-level restrictions, permission management, and audit trail.';
$entryURL    = 'rbac_roles.php';
$type        = 'Additional';
$category    = 'Admin';
$version     = '1.0.00';
$author      = 'LAYA';
$url         = 'https://laya.ca';

// Module tables & gibbonSetting entries
$moduleTables = [];

$moduleTables[] = "CREATE TABLE `gibbonRBACRole` (
    `gibbonRBACRoleID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL UNIQUE,
    `displayName` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `roleType` ENUM('director','teacher','assistant','staff','parent') NOT NULL,
    `isSystemRole` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `sortOrder` INT UNSIGNED NOT NULL DEFAULT 0,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `roleType` (`roleType`),
    KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$moduleTables[] = "CREATE TABLE `gibbonRBACPermission` (
    `gibbonRBACPermissionID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonRBACRoleID` INT UNSIGNED NOT NULL,
    `resource` VARCHAR(100) NOT NULL,
    `action` ENUM('create','read','update','delete','manage') NOT NULL,
    `scope` ENUM('all','own','group') NOT NULL DEFAULT 'own',
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonRBACRoleID` (`gibbonRBACRoleID`),
    KEY `resource` (`resource`),
    KEY `action` (`action`),
    UNIQUE KEY `roleResourceAction` (`gibbonRBACRoleID`, `resource`, `action`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$moduleTables[] = "CREATE TABLE `gibbonRBACUserRole` (
    `gibbonRBACUserRoleID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `gibbonRBACRoleID` INT UNSIGNED NOT NULL,
    `gibbonGroupID` INT UNSIGNED NULL COMMENT 'For group-level role restrictions',
    `assignedByID` INT UNSIGNED NOT NULL COMMENT 'Staff who assigned the role',
    `expiresAt` DATETIME NULL COMMENT 'Optional role expiration',
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `gibbonRBACRoleID` (`gibbonRBACRoleID`),
    KEY `gibbonGroupID` (`gibbonGroupID`),
    KEY `active` (`active`),
    UNIQUE KEY `personRoleGroup` (`gibbonPersonID`, `gibbonRBACRoleID`, `gibbonGroupID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$moduleTables[] = "CREATE TABLE `gibbonRBACAuditLog` (
    `gibbonRBACAuditLogID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'User who performed action',
    `action` ENUM('login','logout','access_granted','access_denied','data_read','data_create','data_update','data_delete','role_assigned','role_revoked') NOT NULL,
    `resourceType` VARCHAR(100) NULL,
    `resourceID` INT UNSIGNED NULL,
    `details` TEXT NULL COMMENT 'JSON encoded details',
    `ipAddress` VARCHAR(45) NULL,
    `userAgent` VARCHAR(255) NULL,
    `organizationID` VARCHAR(36) NULL,
    `success` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `action` (`action`),
    KEY `resourceType` (`resourceType`),
    KEY `timestampCreated` (`timestampCreated`),
    KEY `success` (`success`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// gibbonSetting entries
$gibbonSetting = [];

$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('RBAC', 'enableGroupRestrictions', 'Enable Group Restrictions', 'Enable group-level access restrictions for educators', 'Y');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('RBAC', 'auditLogEnabled', 'Audit Log Enabled', 'Enable comprehensive audit logging for all actions', 'Y');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('RBAC', 'notifyUnauthorizedAccess', 'Notify Unauthorized Access', 'Send notification to directors on unauthorized access attempts', 'Y');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('RBAC', 'sessionTimeout', 'Session Timeout', 'Session timeout in minutes for inactive users', '30');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('RBAC', 'auditRetentionDays', 'Audit Retention Days', 'Number of days to retain audit log entries', '365');";

// Seed data for default system roles
$gibbonSetting[] = "INSERT INTO gibbonRBACRole (name, displayName, description, roleType, isSystemRole, active, sortOrder) VALUES
    ('director', 'Director', 'Full read access to all data. Can manage roles and permissions.', 'director', 'Y', 'Y', 1),
    ('teacher', 'Teacher', 'Access to children and data within assigned groups.', 'teacher', 'Y', 'Y', 2),
    ('assistant', 'Assistant', 'Limited access to children within assigned groups.', 'assistant', 'Y', 'Y', 3),
    ('staff', 'Staff', 'Access to own schedule and limited shared resources.', 'staff', 'Y', 'Y', 4),
    ('parent', 'Parent', 'Access restricted to own children only.', 'parent', 'Y', 'Y', 5)
ON DUPLICATE KEY UPDATE displayName=displayName;";

// Seed default permissions for each role
$gibbonSetting[] = "INSERT INTO gibbonRBACPermission (gibbonRBACRoleID, resource, action, scope, active) VALUES
    ((SELECT gibbonRBACRoleID FROM gibbonRBACRole WHERE name='director'), 'children', 'read', 'all', 'Y'),
    ((SELECT gibbonRBACRoleID FROM gibbonRBACRole WHERE name='director'), 'children', 'manage', 'all', 'Y'),
    ((SELECT gibbonRBACRoleID FROM gibbonRBACRole WHERE name='director'), 'staff', 'read', 'all', 'Y'),
    ((SELECT gibbonRBACRoleID FROM gibbonRBACRole WHERE name='director'), 'staff', 'manage', 'all', 'Y'),
    ((SELECT gibbonRBACRoleID FROM gibbonRBACRole WHERE name='director'), 'roles', 'manage', 'all', 'Y'),
    ((SELECT gibbonRBACRoleID FROM gibbonRBACRole WHERE name='director'), 'audit', 'read', 'all', 'Y'),
    ((SELECT gibbonRBACRoleID FROM gibbonRBACRole WHERE name='director'), 'reports', 'read', 'all', 'Y'),
    ((SELECT gibbonRBACRoleID FROM gibbonRBACRole WHERE name='director'), 'settings', 'manage', 'all', 'Y'),
    ((SELECT gibbonRBACRoleID FROM gibbonRBACRole WHERE name='teacher'), 'children', 'read', 'group', 'Y'),
    ((SELECT gibbonRBACRoleID FROM gibbonRBACRole WHERE name='teacher'), 'children', 'update', 'group', 'Y'),
    ((SELECT gibbonRBACRoleID FROM gibbonRBACRole WHERE name='teacher'), 'activities', 'manage', 'group', 'Y'),
    ((SELECT gibbonRBACRoleID FROM gibbonRBACRole WHERE name='teacher'), 'reports', 'read', 'group', 'Y'),
    ((SELECT gibbonRBACRoleID FROM gibbonRBACRole WHERE name='assistant'), 'children', 'read', 'group', 'Y'),
    ((SELECT gibbonRBACRoleID FROM gibbonRBACRole WHERE name='assistant'), 'activities', 'read', 'group', 'Y'),
    ((SELECT gibbonRBACRoleID FROM gibbonRBACRole WHERE name='staff'), 'schedule', 'read', 'own', 'Y'),
    ((SELECT gibbonRBACRoleID FROM gibbonRBACRole WHERE name='parent'), 'children', 'read', 'own', 'Y'),
    ((SELECT gibbonRBACRoleID FROM gibbonRBACRole WHERE name='parent'), 'reports', 'read', 'own', 'Y')
ON DUPLICATE KEY UPDATE active=active;";

// Action rows for gibbonAction
$actionRows = [];

$actionRows[] = [
    'name'                      => 'Manage Roles',
    'precedence'                => 0,
    'category'                  => 'Access Control',
    'description'               => 'View and manage RBAC roles and their permissions.',
    'URLList'                   => 'rbac_roles.php,rbac_roles_edit.php',
    'entryURL'                  => 'rbac_roles.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Manage User Roles',
    'precedence'                => 0,
    'category'                  => 'Access Control',
    'description'               => 'Assign and revoke roles for users.',
    'URLList'                   => 'rbac_users.php,rbac_assign.php,rbac_revoke.php',
    'entryURL'                  => 'rbac_users.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'View Audit Trail',
    'precedence'                => 0,
    'category'                  => 'Access Control',
    'description'               => 'View comprehensive audit logs of all access and modifications.',
    'URLList'                   => 'rbac_audit.php,rbac_audit_detail.php',
    'entryURL'                  => 'rbac_audit.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'View Permission Matrix',
    'precedence'                => 0,
    'category'                  => 'Access Control',
    'description'               => 'View the complete permission matrix showing all roles and their permissions.',
    'URLList'                   => 'rbac_permissions.php',
    'entryURL'                  => 'rbac_permissions.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'RBAC Settings',
    'precedence'                => 0,
    'category'                  => 'Access Control',
    'description'               => 'Configure RBAC settings including audit logging and notifications.',
    'URLList'                   => 'rbac_settings.php',
    'entryURL'                  => 'rbac_settings.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

// Hooks (optional - for extending existing pages)
$hooks = [];
