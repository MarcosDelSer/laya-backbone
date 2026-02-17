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
//
// TEMPLATE MODULE: This is an example module demonstrating Gibbon module patterns.
// To create a new module:
// 1. Copy this directory and rename to your module name
// 2. Update all variables below with your module details
// 3. Update namespace in src/Domain/ classes
// 4. Implement your specific functionality

// Basic variables
$name        = 'Example Module';
$description = 'Template module demonstrating Gibbon module development patterns (copy and adapt for your module).';
$entryURL    = 'exampleModule.php';
$type        = 'Additional';
$category    = 'Other';
$version     = '1.0.00';
$author      = 'LAYA';
$url         = 'https://laya.ca';

// Module tables & gibbonSetting entries
$moduleTables = [];

// Example table structure - adapt for your module's needs
$moduleTables[] = "CREATE TABLE `gibbonExampleEntity` (
    `gibbonExampleEntityID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `title` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `status` ENUM('Active','Inactive','Pending') NOT NULL DEFAULT 'Active',
    `createdByID` INT UNSIGNED NOT NULL COMMENT 'Staff who created',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `gibbonSchoolYearID` (`gibbonSchoolYearID`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// gibbonSetting entries - module configuration options
$gibbonSetting = [];

$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Example Module', 'enableFeature', 'Enable Feature', 'Enable or disable the example feature', 'Y');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Example Module', 'maxItems', 'Maximum Items', 'Maximum number of items to display per page', '50');";

// Action rows for gibbonAction - defines pages and permissions
$actionRows = [];

$actionRows[] = [
    'name'                      => 'Example Dashboard',
    'precedence'                => 0,
    'category'                  => 'Example',
    'description'               => 'View example module dashboard.',
    'URLList'                   => 'exampleModule.php',
    'entryURL'                  => 'exampleModule.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Manage Example Items',
    'precedence'                => 0,
    'category'                  => 'Example',
    'description'               => 'Create, edit, and delete example items.',
    'URLList'                   => 'exampleModule_manage.php,exampleModule_manage_add.php,exampleModule_manage_edit.php,exampleModule_manage_delete.php',
    'entryURL'                  => 'exampleModule_manage.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'View Example Items',
    'precedence'                => 0,
    'category'                  => 'Example',
    'description'               => 'View example items (read-only access).',
    'URLList'                   => 'exampleModule_view.php',
    'entryURL'                  => 'exampleModule_view.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'Y',
    'defaultPermissionParent'   => 'Y',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Example Settings',
    'precedence'                => 0,
    'category'                  => 'Example',
    'description'               => 'Configure example module settings.',
    'URLList'                   => 'exampleModule_settings.php',
    'entryURL'                  => 'exampleModule_settings.php',
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

// Example hook - uncomment and adapt if needed
// $hooks[] = [
//     'type'                 => 'Dashboard',
//     'name'                 => 'Example Dashboard Widget',
//     'sourceModuleName'     => 'Example Module',
//     'sourceModuleAction'   => 'Example Dashboard',
//     'sourceModuleInclude'  => 'hook_example.php'
// ];
