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

//
// DATABASE MIGRATIONS - ONE-WAY ONLY!
//
// CRITICAL RULES:
// 1. NEVER edit previously committed migration lines
// 2. Always add corrections as NEW statements (append only)
// 3. Each SQL statement MUST end with ';end'
// 4. Always backup database before running migrations
// 5. Use utf8mb4_unicode_ci collation for all tables
// 6. Test migrations in development environment first
//

$sql = [];
$count = 0;

// Version 1.0.00 - Initial schema
$sql[$count][0] = '1.0.00';
$sql[$count][1] = "";
$count++;

// Example: Create initial table (adapt for your module)
// Uncomment and modify when implementing your module
/*
$sql[$count][0] = '1.0.01';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonExampleEntity` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end
";
$count++;
*/

// Example: Add gibbonSetting entries (adapt for your module)
/*
$sql[$count][0] = '1.0.02';
$sql[$count][1] = "
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
VALUES ('Example Module', 'enableFeature', 'Enable Feature', 'Enable or disable the example feature', 'Y')
ON DUPLICATE KEY UPDATE
    nameDisplay = VALUES(nameDisplay),
    description = VALUES(description);end
";
$count++;

$sql[$count][0] = '1.0.03';
$sql[$count][1] = "
INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
VALUES ('Example Module', 'maxItems', 'Maximum Items', 'Maximum number of items to display per page', '50')
ON DUPLICATE KEY UPDATE
    nameDisplay = VALUES(nameDisplay),
    description = VALUES(description);end
";
$count++;
*/

// Example: Add a new column to existing table (migration correction pattern)
// Always add as NEW statement, never edit above
/*
$sql[$count][0] = '1.0.04';
$sql[$count][1] = "
ALTER TABLE `gibbonExampleEntity`
ADD COLUMN `newField` VARCHAR(50) NULL AFTER `description`;end
";
$count++;
*/

// Example: Add hook integration (if module needs hooks)
/*
$sql[$count][0] = '1.0.05';
$sql[$count][1] = "
INSERT INTO gibbonHook (gibbonModuleID, name, type, options)
SELECT gibbonModuleID, 'Example Dashboard Widget', 'Dashboard', ''
FROM gibbonModule
WHERE name='Example Module'
ON DUPLICATE KEY UPDATE name=VALUES(name);end
";
$count++;
*/

//
// MIGRATION NOTES:
//
// - Each version increment represents a database change
// - Use semantic versioning: 1.0.00, 1.0.01, 1.0.02, etc.
// - For module hooks requiring gibbonModuleID, use SELECT FROM gibbonModule
// - Test each migration independently before committing
// - Document complex migrations with comments
// - Use CREATE TABLE IF NOT EXISTS for safety
// - Use ON DUPLICATE KEY UPDATE for gibbonSetting inserts
//
