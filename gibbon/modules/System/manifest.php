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

// This file describes the System module for setup wizard and system configuration

// Basic variables
$name        = 'System';
$description = 'System setup and configuration including first-run wizard.';
$entryURL    = 'setup_wizard.php';
$type        = 'Core';
$category    = 'Admin';
$version     = '1.0.00';
$author      = 'LAYA';
$url         = 'https://laya.ca';

// Module tables & gibbonSetting entries
$moduleTables = [];

$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonSetupWizard` (
    `gibbonSetupWizardID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `stepCompleted` VARCHAR(50) NOT NULL COMMENT 'Last completed step',
    `stepData` JSON NULL COMMENT 'Data for current/completed step',
    `wizardCompleted` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Whether wizard is fully completed',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampUpdated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `wizardCompleted` (`wizardCompleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tracks setup wizard progress and completion';";

// gibbonSetting entries
$gibbonSetting = [];

$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
    VALUES ('System', 'setupWizardCompleted', 'Setup Wizard Completed', 'Whether the first-run setup wizard has been completed', 'N')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
    VALUES ('System', 'setupWizardEnabled', 'Setup Wizard Enabled', 'Enable or disable the setup wizard (disable after first run)', 'Y')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
    VALUES ('System', 'freshInstallation', 'Fresh Installation', 'Indicates if this is a fresh installation requiring setup', 'Y')
    ON DUPLICATE KEY UPDATE scope=scope;";

// Action rows for gibbonAction
$actionRows = [];

$actionRows[] = [
    'name'                      => 'Setup Wizard',
    'precedence'                => 0,
    'category'                  => 'Admin',
    'description'               => 'First-run setup wizard for daycare configuration.',
    'URLList'                   => 'setup_wizard.php',
    'entryURL'                  => 'setup_wizard.php',
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
