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

// Medical Protocol Module - Database Change Log
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = array();
$count = 0;

// v1.0.00 - Initial release with Quebec protocols FO-0647 (Acetaminophen) and FO-0646 (Insect Repellent)
++$count;
$sql[$count][0] = '1.0.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonMedicalProtocol` (
    `gibbonMedicalProtocolID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL COMMENT 'Protocol name (Acetaminophen, Insect Repellent)',
    `formCode` VARCHAR(20) NOT NULL COMMENT 'Quebec form code (FO-0647, FO-0646)',
    `type` ENUM('Medication','Topical') NOT NULL,
    `description` TEXT NOT NULL COMMENT 'Full protocol description and instructions',
    `legalText` TEXT NOT NULL COMMENT 'Legal authorization text for parent consent',
    `dosingInstructions` TEXT NULL COMMENT 'General dosing guidelines (JSON for complex dosing)',
    `ageRestrictionMonths` INT UNSIGNED NULL COMMENT 'Minimum age in months (NULL = no restriction)',
    `intervalMinutes` INT UNSIGNED NULL COMMENT 'Minimum interval between administrations in minutes',
    `maxDailyDoses` INT UNSIGNED NULL COMMENT 'Maximum administrations per 24 hours',
    `requiresTemperature` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Whether temperature reading is required',
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `formCode` (`formCode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonMedicalProtocolDosing` (
    `gibbonMedicalProtocolDosingID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonMedicalProtocolID` INT UNSIGNED NOT NULL,
    `weightMinKg` DECIMAL(5,2) NOT NULL COMMENT 'Minimum weight in kg for this dose range',
    `weightMaxKg` DECIMAL(5,2) NOT NULL COMMENT 'Maximum weight in kg for this dose range',
    `concentration` VARCHAR(50) NOT NULL COMMENT 'e.g., 80mg/mL, 80mg/5mL, 160mg/5mL',
    `doseAmount` DECIMAL(5,2) NOT NULL COMMENT 'Dose amount in mL',
    `doseMg` DECIMAL(6,2) NOT NULL COMMENT 'Equivalent dose in mg',
    `notes` VARCHAR(255) NULL,
    KEY `gibbonMedicalProtocolID` (`gibbonMedicalProtocolID`),
    KEY `weightRange` (`weightMinKg`, `weightMaxKg`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonMedicalProtocolAuthorization` (
    `gibbonMedicalProtocolAuthorizationID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonMedicalProtocolID` INT UNSIGNED NOT NULL,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Child being authorized',
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `authorizedByID` INT UNSIGNED NOT NULL COMMENT 'Parent/guardian who authorized',
    `status` ENUM('Active','Expired','Revoked','Pending') NOT NULL DEFAULT 'Pending',
    `weightKg` DECIMAL(5,2) NOT NULL COMMENT 'Child weight at time of authorization',
    `weightDate` DATE NOT NULL COMMENT 'Date weight was recorded',
    `weightExpiryDate` DATE NOT NULL COMMENT 'Weight must be updated by this date (3 months from weightDate)',
    `signatureData` MEDIUMTEXT NOT NULL COMMENT 'Base64 encoded PNG signature image',
    `signatureDate` DATETIME NOT NULL COMMENT 'When authorization was signed',
    `signatureIP` VARCHAR(45) NULL COMMENT 'IP address at time of signature',
    `agreementText` TEXT NOT NULL COMMENT 'Full legal text agreed to at signing',
    `expiryDate` DATE NULL COMMENT 'Authorization expiry date (end of school year or manual)',
    `revokedDate` DATETIME NULL,
    `revokedByID` INT UNSIGNED NULL,
    `revokedReason` TEXT NULL,
    `notes` TEXT NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `gibbonMedicalProtocolID` (`gibbonMedicalProtocolID`),
    KEY `status` (`status`),
    KEY `weightExpiryDate` (`weightExpiryDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonMedicalProtocolAdministration` (
    `gibbonMedicalProtocolAdministrationID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonMedicalProtocolAuthorizationID` INT UNSIGNED NOT NULL,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Child who received administration',
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `time` TIME NOT NULL,
    `administeredByID` INT UNSIGNED NOT NULL COMMENT 'Staff who administered',
    `witnessedByID` INT UNSIGNED NULL COMMENT 'Staff witness (if required)',
    `doseGiven` VARCHAR(100) NOT NULL COMMENT 'Actual dose administered (e.g., 5 mL)',
    `doseMg` DECIMAL(6,2) NULL COMMENT 'Dose in mg (for medication)',
    `concentration` VARCHAR(50) NULL COMMENT 'Concentration used',
    `weightAtTimeKg` DECIMAL(5,2) NOT NULL COMMENT 'Child weight at time of administration',
    `temperatureC` DECIMAL(4,2) NULL COMMENT 'Temperature in Celsius (for acetaminophen)',
    `temperatureMethod` ENUM('Oral','Axillary','Rectal','Tympanic','Temporal') NULL,
    `reason` TEXT NULL COMMENT 'Reason for administration',
    `observations` TEXT NULL COMMENT 'Post-administration observations',
    `followUpTime` TIME NULL COMMENT 'Scheduled follow-up time (e.g., 60 min recheck)',
    `followUpCompleted` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `followUpNotes` TEXT NULL,
    `parentNotified` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `parentNotifiedTime` DATETIME NULL,
    `parentAcknowledged` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `parentAcknowledgedTime` DATETIME NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `gibbonMedicalProtocolAuthorizationID` (`gibbonMedicalProtocolAuthorizationID`),
    KEY `date` (`date`),
    KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Medical Protocol', 'acetaminophenIntervalMinutes', 'Acetaminophen Minimum Interval', 'Minimum time in minutes between acetaminophen doses', '240') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Medical Protocol', 'acetaminophenMaxDailyDoses', 'Acetaminophen Max Daily Doses', 'Maximum number of acetaminophen doses per 24 hours', '5') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Medical Protocol', 'weightValidityMonths', 'Weight Validity Period', 'Number of months before weight must be re-validated', '3') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Medical Protocol', 'insectRepellentMinAgeMonths', 'Insect Repellent Minimum Age', 'Minimum age in months for insect repellent application', '6') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Medical Protocol', 'notifyParentOnAdministration', 'Notify Parent on Administration', 'Automatically notify parents when protocol is administered', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Medical Protocol', 'requireFollowUpCheck', 'Require Follow-up Check', 'Require 60-minute follow-up check after acetaminophen', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end

INSERT INTO `gibbonMedicalProtocol` (`name`, `formCode`, `type`, `description`, `legalText`, `dosingInstructions`, `ageRestrictionMonths`, `intervalMinutes`, `maxDailyDoses`, `requiresTemperature`, `active`) VALUES
('Acetaminophen', 'FO-0647', 'Medication',
'Quebec-mandated protocol for acetaminophen administration to reduce fever in children. Follows 10-step administration procedure with temperature measurement, weight-based dosing, and 60-minute recheck.',
'I authorize the childcare facility to administer acetaminophen to my child according to the protocol FO-0647 established by the Ministère de la Famille du Québec. I confirm that my child has no known allergies or contraindications to acetaminophen. I understand that medication will only be administered when my child presents with a fever as defined by age-appropriate temperature thresholds.',
'{\"dosePerKg\":{\"min\":10,\"max\":15,\"unit\":\"mg\"},\"concentrations\":[\"80mg/mL\",\"80mg/5mL\",\"160mg/5mL\"]}',
NULL, 240, 5, 'Y', 'Y'),
('Insect Repellent', 'FO-0646', 'Topical',
'Quebec-mandated protocol for insect repellent application. DEET maximum 10%, Picaridin maximum 20%. Application restricted to exposed skin only, avoiding hands, eyes, mouth, and cuts.',
'I authorize the childcare facility to apply insect repellent to my child according to the protocol FO-0646 established by the Ministère de la Famille du Québec. I confirm that my child has no known allergies or sensitivities to DEET or Picaridin-based products. I understand that repellent will only be applied to exposed skin areas as needed for outdoor activities.',
'{\"maxDEET\":10,\"maxPicaridin\":20,\"prohibitedAreas\":[\"hands\",\"eyes\",\"mouth\",\"cuts\",\"irritated skin\"]}',
6, NULL, NULL, 'N', 'Y');end

INSERT INTO `gibbonMedicalProtocolDosing` (`gibbonMedicalProtocolID`, `weightMinKg`, `weightMaxKg`, `concentration`, `doseAmount`, `doseMg`, `notes`) VALUES
(1, 4.30, 5.30, '80mg/mL', 0.5, 40, 'Drops'),
(1, 5.40, 7.90, '80mg/mL', 1.0, 80, 'Drops'),
(1, 8.00, 10.90, '80mg/mL', 1.5, 120, 'Drops'),
(1, 11.00, 15.90, '80mg/mL', 2.0, 160, 'Drops'),
(1, 16.00, 21.90, '80mg/mL', 2.5, 200, 'Drops'),
(1, 22.00, 26.90, '80mg/mL', 3.0, 240, 'Drops'),
(1, 27.00, 31.90, '80mg/mL', 3.5, 280, 'Drops'),
(1, 32.00, 35.00, '80mg/mL', 4.0, 320, 'Drops');end

INSERT INTO `gibbonMedicalProtocolDosing` (`gibbonMedicalProtocolID`, `weightMinKg`, `weightMaxKg`, `concentration`, `doseAmount`, `doseMg`, `notes`) VALUES
(1, 4.30, 5.30, '80mg/5mL', 2.5, 40, 'Syrup'),
(1, 5.40, 7.90, '80mg/5mL', 5.0, 80, 'Syrup'),
(1, 8.00, 10.90, '80mg/5mL', 7.5, 120, 'Syrup'),
(1, 11.00, 15.90, '80mg/5mL', 10.0, 160, 'Syrup'),
(1, 16.00, 21.90, '80mg/5mL', 12.5, 200, 'Syrup'),
(1, 22.00, 26.90, '80mg/5mL', 15.0, 240, 'Syrup'),
(1, 27.00, 31.90, '80mg/5mL', 17.5, 280, 'Syrup'),
(1, 32.00, 35.00, '80mg/5mL', 20.0, 320, 'Syrup');end

INSERT INTO `gibbonMedicalProtocolDosing` (`gibbonMedicalProtocolID`, `weightMinKg`, `weightMaxKg`, `concentration`, `doseAmount`, `doseMg`, `notes`) VALUES
(1, 4.30, 5.30, '160mg/5mL', 1.25, 40, 'Concentrated syrup'),
(1, 5.40, 7.90, '160mg/5mL', 2.5, 80, 'Concentrated syrup'),
(1, 8.00, 10.90, '160mg/5mL', 3.75, 120, 'Concentrated syrup'),
(1, 11.00, 15.90, '160mg/5mL', 5.0, 160, 'Concentrated syrup'),
(1, 16.00, 21.90, '160mg/5mL', 6.25, 200, 'Concentrated syrup'),
(1, 22.00, 26.90, '160mg/5mL', 7.5, 240, 'Concentrated syrup'),
(1, 27.00, 31.90, '160mg/5mL', 8.75, 280, 'Concentrated syrup'),
(1, 32.00, 35.00, '160mg/5mL', 10.0, 320, 'Concentrated syrup');end
";

// v1.0.01 - Bug fixes and minor improvements
++$count;
$sql[$count][0] = '1.0.01';
$sql[$count][1] = "";

// v1.1.00 - Audit logging and compliance tracking enhancements
++$count;
$sql[$count][0] = '1.1.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonMedicalProtocolAuditLog` (
    `gibbonMedicalProtocolAuditLogID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
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

ALTER TABLE `gibbonMedicalProtocolAdministration` ADD COLUMN `lotNumber` VARCHAR(50) NULL COMMENT 'Medication lot number' AFTER `concentration`;end
ALTER TABLE `gibbonMedicalProtocolAdministration` ADD COLUMN `expirationDate` DATE NULL COMMENT 'Medication expiration date' AFTER `lotNumber`;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Medical Protocol', 'auditLoggingEnabled', 'Audit Logging Enabled', 'Enable detailed audit logging for compliance', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Medical Protocol', 'requireLotNumber', 'Require Lot Number', 'Require medication lot number when administering', 'N') ON DUPLICATE KEY UPDATE scope=scope;end
";

// v1.2.00 - Weight history tracking and notification preferences
++$count;
$sql[$count][0] = '1.2.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonMedicalProtocolWeightHistory` (
    `gibbonMedicalProtocolWeightHistoryID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Child',
    `weightKg` DECIMAL(5,2) NOT NULL,
    `recordedDate` DATE NOT NULL,
    `recordedByID` INT UNSIGNED NOT NULL COMMENT 'Staff or parent who recorded',
    `source` ENUM('Authorization','Manual','Import') NOT NULL DEFAULT 'Manual',
    `notes` TEXT NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `recordedDate` (`recordedDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonMedicalProtocolNotification` (
    `gibbonMedicalProtocolNotificationID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Child related to notification',
    `recipientPersonID` INT UNSIGNED NOT NULL COMMENT 'Parent/guardian receiving notification',
    `type` ENUM('Administration','WeightExpiring','AuthorizationExpiring','AuthorizationRequired') NOT NULL,
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

ALTER TABLE `gibbonMedicalProtocolAuthorization` ADD COLUMN `allergiesConfirmed` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Parent confirmed no allergies' AFTER `notes`;end
ALTER TABLE `gibbonMedicalProtocolAuthorization` ADD COLUMN `preSeasonTestDate` DATE NULL COMMENT 'Pre-season allergy test date (for insect repellent)' AFTER `allergiesConfirmed`;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Medical Protocol', 'weightExpiryWarningDays', 'Weight Expiry Warning Days', 'Days before weight expiry to send reminder notification', '14') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Medical Protocol', 'requirePreSeasonTest', 'Require Pre-Season Allergy Test', 'Require pre-season allergy test for insect repellent', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
";

// v1.3.00 - Temperature thresholds by age group per FO-0647
++$count;
$sql[$count][0] = '1.3.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonMedicalProtocolTemperatureThreshold` (
    `gibbonMedicalProtocolTemperatureThresholdID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonMedicalProtocolID` INT UNSIGNED NOT NULL,
    `ageMinMonths` INT UNSIGNED NOT NULL COMMENT 'Minimum age in months',
    `ageMaxMonths` INT UNSIGNED NULL COMMENT 'Maximum age in months (NULL = no upper limit)',
    `method` ENUM('Oral','Axillary','Rectal','Tympanic','Temporal') NOT NULL,
    `feverThresholdC` DECIMAL(4,2) NOT NULL COMMENT 'Temperature in Celsius to consider fever',
    `notes` VARCHAR(255) NULL,
    KEY `gibbonMedicalProtocolID` (`gibbonMedicalProtocolID`),
    KEY `ageRange` (`ageMinMonths`, `ageMaxMonths`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonMedicalProtocolTemperatureThreshold` (`gibbonMedicalProtocolID`, `ageMinMonths`, `ageMaxMonths`, `method`, `feverThresholdC`, `notes`) VALUES
(1, 0, 24, 'Rectal', 38.5, 'Recommended for infants and toddlers'),
(1, 0, 24, 'Axillary', 37.5, 'Add 0.5C for rectal equivalent'),
(1, 0, 24, 'Tympanic', 38.0, 'Ear thermometer'),
(1, 24, NULL, 'Oral', 38.0, 'For children 2+ years'),
(1, 24, NULL, 'Axillary', 37.5, 'Add 0.5C for oral equivalent'),
(1, 24, NULL, 'Tympanic', 38.0, 'Ear thermometer'),
(1, 24, NULL, 'Temporal', 38.0, 'Forehead thermometer');end

ALTER TABLE `gibbonMedicalProtocolAdministration` ADD COLUMN `feverConfirmed` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Temperature exceeded threshold' AFTER `temperatureMethod`;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Medical Protocol', 'requireFeverConfirmation', 'Require Fever Confirmation', 'Require temperature above threshold before acetaminophen administration', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
";
