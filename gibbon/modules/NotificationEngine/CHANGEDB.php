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
CREATE TABLE IF NOT EXISTS `gibbonNotificationQueue` (
    `gibbonNotificationQueueID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Recipient',
    `type` VARCHAR(50) NOT NULL COMMENT 'checkIn, photo, incident, etc.',
    `title` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `data` JSON NULL COMMENT 'Additional payload data',
    `channel` ENUM('email','push','both') NOT NULL DEFAULT 'both',
    `status` ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
    `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `lastAttemptAt` DATETIME NULL,
    `sentAt` DATETIME NULL,
    `errorMessage` TEXT NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `status` (`status`),
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `type` (`type`),
    KEY `status_attempts` (`status`, `attempts`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonNotificationTemplate` (
    `gibbonNotificationTemplateID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `type` VARCHAR(50) NOT NULL UNIQUE,
    `nameDisplay` VARCHAR(100) NOT NULL,
    `subjectTemplate` VARCHAR(255) NOT NULL,
    `bodyTemplate` TEXT NOT NULL,
    `pushTitle` VARCHAR(100) NULL COMMENT 'Optional shorter title for push notifications',
    `pushBody` VARCHAR(255) NULL COMMENT 'Optional shorter body for push notifications',
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonNotificationPreference` (
    `gibbonNotificationPreferenceID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `type` VARCHAR(50) NOT NULL,
    `emailEnabled` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `pushEnabled` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `personType` (`gibbonPersonID`, `type`),
    KEY `gibbonPersonID` (`gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonFCMToken` (
    `gibbonFCMTokenID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `deviceToken` VARCHAR(255) NOT NULL,
    `deviceType` ENUM('ios','android','web') NOT NULL,
    `deviceName` VARCHAR(100) NULL COMMENT 'User-friendly device identifier',
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `lastUsedAt` DATETIME NULL COMMENT 'Last time token was used for sending',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `token` (`deviceToken`),
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `active_person` (`active`, `gibbonPersonID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
VALUES ('Notification Engine', 'fcmEnabled', 'Firebase Push Notifications', 'Enable Firebase Cloud Messaging (FCM) push notifications', 'Y')
ON DUPLICATE KEY UPDATE scope=scope;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
VALUES ('Notification Engine', 'emailEnabled', 'Email Notifications', 'Enable email notifications using Gibbon Mailer', 'Y')
ON DUPLICATE KEY UPDATE scope=scope;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
VALUES ('Notification Engine', 'maxRetryAttempts', 'Max Retry Attempts', 'Maximum number of retry attempts for failed notifications', '3')
ON DUPLICATE KEY UPDATE scope=scope;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
VALUES ('Notification Engine', 'queueBatchSize', 'Queue Batch Size', 'Number of notifications to process per queue run', '50')
ON DUPLICATE KEY UPDATE scope=scope;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
VALUES ('Notification Engine', 'retryDelayMinutes', 'Retry Delay (Minutes)', 'Base delay in minutes before retrying a failed notification (exponential backoff applied)', '5')
ON DUPLICATE KEY UPDATE scope=scope;end

-- Insert default notification templates for LAYA kindergarten events
INSERT INTO `gibbonNotificationTemplate` (`type`, `nameDisplay`, `subjectTemplate`, `bodyTemplate`, `pushTitle`, `pushBody`, `active`)
VALUES ('checkIn', 'Child Check-In', '[{{childName}}] has arrived at {{schoolName}}', 'Hello {{parentName}},\n\nThis is to confirm that {{childName}} has been checked in at {{schoolName}} by {{staffName}} at {{checkInTime}}.\n\nBest regards,\n{{schoolName}}', 'Check-In Confirmed', '{{childName}} has arrived at {{checkInTime}}', 'Y')
ON DUPLICATE KEY UPDATE nameDisplay=nameDisplay;end

INSERT INTO `gibbonNotificationTemplate` (`type`, `nameDisplay`, `subjectTemplate`, `bodyTemplate`, `pushTitle`, `pushBody`, `active`)
VALUES ('checkOut', 'Child Check-Out', '[{{childName}}] has left {{schoolName}}', 'Hello {{parentName}},\n\nThis is to confirm that {{childName}} has been checked out from {{schoolName}} by {{pickupName}} at {{checkOutTime}}.\n\nBest regards,\n{{schoolName}}', 'Check-Out Confirmed', '{{childName}} left at {{checkOutTime}}', 'Y')
ON DUPLICATE KEY UPDATE nameDisplay=nameDisplay;end

INSERT INTO `gibbonNotificationTemplate` (`type`, `nameDisplay`, `subjectTemplate`, `bodyTemplate`, `pushTitle`, `pushBody`, `active`)
VALUES ('photo', 'New Photo Added', 'New photos of {{childName}} available', 'Hello {{parentName}},\n\nNew photos featuring {{childName}} have been added to the gallery.\n\nYou can view them by logging into the parent portal.\n\nBest regards,\n{{schoolName}}', 'New Photos', 'New photos of {{childName}} available', 'Y')
ON DUPLICATE KEY UPDATE nameDisplay=nameDisplay;end

INSERT INTO `gibbonNotificationTemplate` (`type`, `nameDisplay`, `subjectTemplate`, `bodyTemplate`, `pushTitle`, `pushBody`, `active`)
VALUES ('incident', 'Incident Report', 'Incident Report for {{childName}}', 'Hello {{parentName}},\n\nAn incident involving {{childName}} has been reported at {{schoolName}}.\n\nIncident Type: {{incidentType}}\nDate/Time: {{incidentTime}}\nDescription: {{incidentDescription}}\n\nStaff Member: {{staffName}}\n\nPlease contact us if you have any questions.\n\nBest regards,\n{{schoolName}}', 'Incident Report', 'Incident reported for {{childName}}', 'Y')
ON DUPLICATE KEY UPDATE nameDisplay=nameDisplay;end

INSERT INTO `gibbonNotificationTemplate` (`type`, `nameDisplay`, `subjectTemplate`, `bodyTemplate`, `pushTitle`, `pushBody`, `active`)
VALUES ('meal', 'Meal Report', 'Meal update for {{childName}}', 'Hello {{parentName}},\n\n{{childName}}'s meal information for today:\n\nMeal: {{mealType}}\nPortion: {{portionEaten}}\nNotes: {{mealNotes}}\n\nBest regards,\n{{schoolName}}', 'Meal Update', '{{childName}}: {{mealType}} - {{portionEaten}}', 'Y')
ON DUPLICATE KEY UPDATE nameDisplay=nameDisplay;end

INSERT INTO `gibbonNotificationTemplate` (`type`, `nameDisplay`, `subjectTemplate`, `bodyTemplate`, `pushTitle`, `pushBody`, `active`)
VALUES ('nap', 'Nap Report', 'Nap update for {{childName}}', 'Hello {{parentName}},\n\n{{childName}}'s nap information:\n\nStart Time: {{napStart}}\nEnd Time: {{napEnd}}\nDuration: {{napDuration}}\nNotes: {{napNotes}}\n\nBest regards,\n{{schoolName}}', 'Nap Update', '{{childName}} napped for {{napDuration}}', 'Y')
ON DUPLICATE KEY UPDATE nameDisplay=nameDisplay;end

INSERT INTO `gibbonNotificationTemplate` (`type`, `nameDisplay`, `subjectTemplate`, `bodyTemplate`, `pushTitle`, `pushBody`, `active`)
VALUES ('announcement', 'General Announcement', '{{schoolName}}: {{announcementTitle}}', 'Hello {{parentName}},\n\n{{announcementBody}}\n\nBest regards,\n{{schoolName}}', '{{announcementTitle}}', '{{announcementSummary}}', 'Y')
ON DUPLICATE KEY UPDATE nameDisplay=nameDisplay;end
";

// v1.1.00 - Add readAt column for notification inbox
++$count;
$sql[$count][0] = '1.1.00';
$sql[$count][1] = "
ALTER TABLE `gibbonNotificationQueue`
ADD COLUMN `readAt` DATETIME NULL COMMENT 'Timestamp when notification was marked as read' AFTER `sentAt`;end

CREATE INDEX `idx_readAt` ON `gibbonNotificationQueue` (`readAt`);end
";
