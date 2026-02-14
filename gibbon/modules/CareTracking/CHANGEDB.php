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

// Care Tracking Module - Database Change Log
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = array();
$count = 0;

// v1.0.00 - Initial release
++$count;
$sql[$count][0] = '1.0.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonCareAttendance` (
    `gibbonCareAttendanceID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `checkInTime` TIME NULL,
    `checkOutTime` TIME NULL,
    `checkInByID` INT UNSIGNED NULL COMMENT 'Staff who checked in',
    `checkOutByID` INT UNSIGNED NULL COMMENT 'Staff who checked out',
    `pickupPersonName` VARCHAR(100) NULL COMMENT 'Name of authorized pickup person',
    `notes` TEXT NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `date` (`date`),
    KEY `gibbonSchoolYearID` (`gibbonSchoolYearID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonCareMeal` (
    `gibbonCareMealID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `mealType` ENUM('Breakfast','Morning Snack','Lunch','Afternoon Snack','Dinner') NOT NULL,
    `quantity` ENUM('None','Little','Some','Most','All') NOT NULL DEFAULT 'Some',
    `notes` TEXT NULL,
    `recordedByID` INT UNSIGNED NOT NULL COMMENT 'Staff who recorded',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `date` (`date`),
    KEY `mealType` (`mealType`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonCareNap` (
    `gibbonCareNapID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `startTime` TIME NOT NULL,
    `endTime` TIME NULL,
    `quality` ENUM('Restless','Light','Sound') NULL,
    `notes` TEXT NULL,
    `recordedByID` INT UNSIGNED NOT NULL COMMENT 'Staff who recorded',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonCareDiaper` (
    `gibbonCareDiaperID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `time` TIME NOT NULL,
    `type` ENUM('Wet','Soiled','Both','Dry') NOT NULL,
    `notes` TEXT NULL,
    `recordedByID` INT UNSIGNED NOT NULL COMMENT 'Staff who recorded',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonCareIncident` (
    `gibbonCareIncidentID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `time` TIME NOT NULL,
    `type` ENUM('Minor Injury','Major Injury','Illness','Behavioral','Other') NOT NULL,
    `description` TEXT NOT NULL,
    `actionTaken` TEXT NULL,
    `parentNotified` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `parentNotifiedTime` DATETIME NULL,
    `parentAcknowledged` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `parentAcknowledgedTime` DATETIME NULL,
    `recordedByID` INT UNSIGNED NOT NULL COMMENT 'Staff who recorded',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `date` (`date`),
    KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonCareActivity` (
    `gibbonCareActivityID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `activityName` VARCHAR(100) NOT NULL,
    `activityType` ENUM('Art','Music','Physical','Language','Math','Science','Social','Free Play','Outdoor','Other') NOT NULL,
    `duration` INT UNSIGNED NULL COMMENT 'Duration in minutes',
    `participation` ENUM('Not Interested','Observing','Participating','Leading') NULL,
    `notes` TEXT NULL,
    `recordedByID` INT UNSIGNED NOT NULL COMMENT 'Staff who recorded',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `date` (`date`),
    KEY `activityType` (`activityType`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Care Tracking', 'mealTypes', 'Meal Types', 'Comma-separated list of meal types to track', 'Breakfast,Morning Snack,Lunch,Afternoon Snack,Dinner') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Care Tracking', 'napMinDuration', 'Minimum Nap Duration', 'Minimum nap duration in minutes to display alert', '30') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Care Tracking', 'incidentNotifyParent', 'Auto-notify Parent on Incident', 'Automatically send notification to parents when incident is logged', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Care Tracking', 'defaultCheckInTime', 'Default Check-In Time', 'Default check-in time for attendance (HH:MM format)', '08:00') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Care Tracking', 'defaultCheckOutTime', 'Default Check-Out Time', 'Default check-out time for attendance (HH:MM format)', '17:00') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Care Tracking', 'trackingMode', 'Tracking Mode', 'Mode for daily care tracking interface', 'Individual') ON DUPLICATE KEY UPDATE scope=scope;end
";

// v1.0.01 - Bug fixes and minor improvements
++$count;
$sql[$count][0] = '1.0.01';
$sql[$count][1] = "";

// v1.1.00 - Photo association and AI integration support
++$count;
$sql[$count][0] = '1.1.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonCarePhoto` (
    `gibbonCarePhotoID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Child in photo',
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `photoPath` VARCHAR(255) NOT NULL,
    `caption` TEXT NULL,
    `activityContext` VARCHAR(100) NULL COMMENT 'Activity related to photo',
    `sharedWithParent` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `uploadedByID` INT UNSIGNED NOT NULL COMMENT 'Staff who uploaded',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `deletedAt` DATETIME NULL COMMENT 'Soft delete for retention',
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `date` (`date`),
    KEY `deletedAt` (`deletedAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

ALTER TABLE `gibbonCareActivity` ADD COLUMN `aiSuggested` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `participation`;end
ALTER TABLE `gibbonCareActivity` ADD COLUMN `aiActivityID` INT UNSIGNED NULL COMMENT 'Reference to AI activity suggestion' AFTER `aiSuggested`;end
ALTER TABLE `gibbonCareIncident` ADD COLUMN `severity` ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Low' AFTER `type`;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Care Tracking', 'photoRetentionYears', 'Photo Retention Years', 'Number of years to retain photos before purging', '5') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Care Tracking', 'aiIntegrationEnabled', 'AI Integration Enabled', 'Enable AI-powered activity suggestions', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
";

// v1.2.00 - Daily summary and reporting enhancements
++$count;
$sql[$count][0] = '1.2.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonCareDailySummary` (
    `gibbonCareDailySummaryID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `mood` ENUM('Happy','Content','Tired','Fussy','Unwell') NULL,
    `overallNotes` TEXT NULL,
    `aiGeneratedSummary` TEXT NULL COMMENT 'AI-generated daily summary',
    `sentToParent` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `sentToParentTime` DATETIME NULL,
    `createdByID` INT UNSIGNED NOT NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `personDate` (`gibbonPersonID`, `date`),
    KEY `gibbonSchoolYearID` (`gibbonSchoolYearID`),
    KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

ALTER TABLE `gibbonCareAttendance` ADD COLUMN `lateArrival` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `checkOutTime`;end
ALTER TABLE `gibbonCareAttendance` ADD COLUMN `earlyDeparture` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `lateArrival`;end
ALTER TABLE `gibbonCareAttendance` ADD COLUMN `absenceReason` VARCHAR(100) NULL AFTER `earlyDeparture`;end
ALTER TABLE `gibbonCareMeal` ADD COLUMN `allergyAlert` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `quantity`;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Care Tracking', 'lateArrivalThreshold', 'Late Arrival Threshold', 'Minutes after default check-in time to mark as late', '15') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Care Tracking', 'autoSendDailySummary', 'Auto Send Daily Summary', 'Automatically send daily summary to parents at end of day', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Care Tracking', 'dailySummarySendTime', 'Daily Summary Send Time', 'Time to automatically send daily summaries (HH:MM format)', '17:30') ON DUPLICATE KEY UPDATE scope=scope;end
";

// v1.3.00 - Authorized pickup and enhanced security
++$count;
$sql[$count][0] = '1.3.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonCareAuthorizedPickup` (
    `gibbonCareAuthorizedPickupID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Child being picked up',
    `pickupPersonName` VARCHAR(100) NOT NULL,
    `relationship` VARCHAR(50) NOT NULL,
    `phone` VARCHAR(20) NULL,
    `photoPath` VARCHAR(255) NULL COMMENT 'Photo for identification',
    `identificationVerified` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `addedByID` INT UNSIGNED NOT NULL COMMENT 'Staff/parent who added',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

ALTER TABLE `gibbonCareAttendance` ADD COLUMN `gibbonCareAuthorizedPickupID` INT UNSIGNED NULL AFTER `pickupPersonName`;end
ALTER TABLE `gibbonCareAttendance` ADD COLUMN `pickupVerified` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `gibbonCareAuthorizedPickupID`;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Care Tracking', 'requirePickupVerification', 'Require Pickup Verification', 'Require verification of authorized pickup person at check-out', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Care Tracking', 'allowUnauthorizedPickup', 'Allow Unauthorized Pickup', 'Allow pickup by persons not on authorized list with director approval', 'N') ON DUPLICATE KEY UPDATE scope=scope;end
";

// v1.4.00 - Notification integration and audit logging
++$count;
$sql[$count][0] = '1.4.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonCareNotification` (
    `gibbonCareNotificationID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Child related to notification',
    `recipientPersonID` INT UNSIGNED NOT NULL COMMENT 'Parent/guardian receiving notification',
    `type` ENUM('CheckIn','CheckOut','Meal','Nap','Incident','Photo','DailySummary') NOT NULL,
    `referenceID` INT UNSIGNED NULL COMMENT 'ID of related record',
    `message` TEXT NOT NULL,
    `deliveryMethod` ENUM('Email','Push','Both') NOT NULL DEFAULT 'Both',
    `status` ENUM('Pending','Sent','Failed','Read') NOT NULL DEFAULT 'Pending',
    `sentAt` DATETIME NULL,
    `readAt` DATETIME NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `recipientPersonID` (`recipientPersonID`),
    KEY `status` (`status`),
    KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonCareAuditLog` (
    `gibbonCareAuditLogID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tableName` VARCHAR(50) NOT NULL,
    `recordID` INT UNSIGNED NOT NULL,
    `action` ENUM('INSERT','UPDATE','DELETE') NOT NULL,
    `fieldName` VARCHAR(50) NULL,
    `oldValue` TEXT NULL,
    `newValue` TEXT NULL,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'User who made change',
    `ipAddress` VARCHAR(45) NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `tableName` (`tableName`),
    KEY `recordID` (`recordID`),
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `timestampCreated` (`timestampCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Care Tracking', 'auditLoggingEnabled', 'Audit Logging Enabled', 'Enable detailed audit logging for compliance', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Care Tracking', 'notifyOnCheckIn', 'Notify Parents on Check-In', 'Send notification to parents when child is checked in', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Care Tracking', 'notifyOnCheckOut', 'Notify Parents on Check-Out', 'Send notification to parents when child is checked out', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Care Tracking', 'notifyOnIncident', 'Notify Parents on Incident', 'Send notification to parents when incident is logged', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
";
