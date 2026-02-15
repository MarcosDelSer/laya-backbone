<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuiber and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Gibbon Core) and Gibbon LAYA are trademarks of Gibbon Education Ltd.

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

// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = [];
$count = 0;

// v1.0.00 - Initial release
++$count;
$sql[$count][0] = '1.0.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonPhotoUpload` (
    `gibbonPhotoUploadID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `filePath` VARCHAR(255) NOT NULL,
    `caption` TEXT NULL,
    `mimeType` VARCHAR(50) NOT NULL,
    `fileSize` INT UNSIGNED NOT NULL,
    `uploadedByID` INT UNSIGNED NOT NULL,
    `sharedWithParent` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `deletedAt` DATETIME NULL COMMENT 'Soft delete for 5-year Quebec retention compliance',
    `deletedByID` INT UNSIGNED NULL,
    KEY `gibbonSchoolYearID` (`gibbonSchoolYearID`),
    KEY `deletedAt` (`deletedAt`),
    KEY `uploadedByID` (`uploadedByID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonPhotoTag` (
    `gibbonPhotoTagID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPhotoUploadID` INT UNSIGNED NOT NULL,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Child tagged in photo',
    `taggedByID` INT UNSIGNED NOT NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `photoChild` (`gibbonPhotoUploadID`, `gibbonPersonID`),
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `gibbonPhotoUploadID` (`gibbonPhotoUploadID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonPhotoRetention` (
    `gibbonPhotoRetentionID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `lastPurgeRun` DATETIME NULL,
    `recordsPurged` INT UNSIGNED NOT NULL DEFAULT 0,
    `retentionYears` INT UNSIGNED NOT NULL DEFAULT 5,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
VALUES ('Photo Management', 'photoRetentionYears', 'Photo Retention Years', 'Number of years to retain deleted photos before permanent deletion (Quebec compliance requires 5 years)', '5')
ON DUPLICATE KEY UPDATE scope=scope;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
VALUES ('Photo Management', 'photoMaxSizeMB', 'Maximum Photo Size (MB)', 'Maximum file size for uploaded photos in megabytes', '10')
ON DUPLICATE KEY UPDATE scope=scope;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
VALUES ('Photo Management', 'photoAllowedTypes', 'Allowed Photo Types', 'Comma-separated list of allowed file extensions', 'jpg,jpeg,png,gif')
ON DUPLICATE KEY UPDATE scope=scope;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
VALUES ('Photo Management', 'photoDefaultShareWithParent', 'Default Share with Parents', 'Whether new photos are shared with parents by default', 'Y')
ON DUPLICATE KEY UPDATE scope=scope;end

INSERT INTO `gibbonPhotoRetention` (`retentionYears`) VALUES (5)
ON DUPLICATE KEY UPDATE retentionYears=retentionYears;end
";
