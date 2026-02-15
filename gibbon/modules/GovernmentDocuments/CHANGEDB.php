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

// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = [];
$count = 0;

// v1.0.00 - Initial release
++$count;
$sql[$count][0] = '1.0.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonGovernmentDocumentType` (
    `gibbonGovernmentDocumentTypeID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `nameDisplay` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `category` ENUM('Child','Parent','Guardian','Staff') NOT NULL DEFAULT 'Child',
    `required` ENUM('Y','N') NOT NULL DEFAULT 'Y' COMMENT 'Whether document is mandatory',
    `expiryRequired` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Whether expiry date tracking is needed',
    `expiryWarningDays` INT UNSIGNED NULL DEFAULT 30 COMMENT 'Days before expiry to send warning',
    `allowedFileTypes` VARCHAR(255) NOT NULL DEFAULT 'pdf,jpg,jpeg,png' COMMENT 'Comma-separated file extensions',
    `maxFileSizeMB` INT UNSIGNED NOT NULL DEFAULT 10,
    `sequenceNumber` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Display order',
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `name` (`name`),
    KEY `category` (`category`),
    KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonGovernmentDocument` (
    `gibbonGovernmentDocumentID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonGovernmentDocumentTypeID` INT UNSIGNED NOT NULL,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Person the document belongs to',
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `documentNumber` VARCHAR(100) NULL COMMENT 'Document reference number if applicable',
    `issueDate` DATE NULL,
    `expiryDate` DATE NULL,
    `filePath` VARCHAR(500) NOT NULL COMMENT 'Path to uploaded document',
    `originalFileName` VARCHAR(255) NOT NULL,
    `fileSize` INT UNSIGNED NOT NULL COMMENT 'File size in bytes',
    `mimeType` VARCHAR(100) NOT NULL,
    `status` ENUM('pending','verified','rejected','expired') NOT NULL DEFAULT 'pending',
    `verifiedByID` INT UNSIGNED NULL COMMENT 'Staff who verified',
    `verifiedAt` DATETIME NULL,
    `rejectionReason` TEXT NULL,
    `notes` TEXT NULL,
    `uploadedByID` INT UNSIGNED NOT NULL COMMENT 'Person who uploaded',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonGovernmentDocumentTypeID` (`gibbonGovernmentDocumentTypeID`),
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `gibbonSchoolYearID` (`gibbonSchoolYearID`),
    KEY `status` (`status`),
    KEY `expiryDate` (`expiryDate`),
    KEY `person_type` (`gibbonPersonID`, `gibbonGovernmentDocumentTypeID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonGovernmentDocumentLog` (
    `gibbonGovernmentDocumentLogID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonGovernmentDocumentID` INT UNSIGNED NOT NULL,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Person who performed action',
    `action` ENUM('uploaded','viewed','verified','rejected','updated','deleted','expired','expiry_warning_sent') NOT NULL,
    `previousStatus` ENUM('pending','verified','rejected','expired') NULL,
    `newStatus` ENUM('pending','verified','rejected','expired') NULL,
    `details` TEXT NULL COMMENT 'Additional action details or notes',
    `ipAddress` VARCHAR(45) NULL,
    `userAgent` VARCHAR(500) NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonGovernmentDocumentID` (`gibbonGovernmentDocumentID`),
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `action` (`action`),
    KEY `timestampCreated` (`timestampCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
VALUES ('Government Documents', 'expiryCheckEnabled', 'Expiry Check Enabled', 'Enable automatic checking for expiring documents', 'Y')
ON DUPLICATE KEY UPDATE scope=scope;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
VALUES ('Government Documents', 'expiryWarningDays', 'Default Expiry Warning Days', 'Default number of days before expiry to send warnings', '30')
ON DUPLICATE KEY UPDATE scope=scope;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
VALUES ('Government Documents', 'autoNotifyParents', 'Auto-notify Parents', 'Automatically notify parents about missing or expiring documents', 'Y')
ON DUPLICATE KEY UPDATE scope=scope;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
VALUES ('Government Documents', 'requireVerification', 'Require Staff Verification', 'Require staff verification before document is marked as complete', 'Y')
ON DUPLICATE KEY UPDATE scope=scope;end

-- Insert default document types for Quebec childcare compliance
INSERT INTO `gibbonGovernmentDocumentType` (`name`, `nameDisplay`, `description`, `category`, `required`, `expiryRequired`, `expiryWarningDays`, `sequenceNumber`, `active`)
VALUES ('child_birth_certificate', 'Child Birth Certificate', 'Official birth certificate for the child', 'Child', 'Y', 'N', NULL, 1, 'Y')
ON DUPLICATE KEY UPDATE nameDisplay=nameDisplay;end

INSERT INTO `gibbonGovernmentDocumentType` (`name`, `nameDisplay`, `description`, `category`, `required`, `expiryRequired`, `expiryWarningDays`, `sequenceNumber`, `active`)
VALUES ('child_citizenship_proof', 'Child Citizenship/Immigration Proof', 'Proof of citizenship or immigration status for the child', 'Child', 'Y', 'Y', 60, 2, 'Y')
ON DUPLICATE KEY UPDATE nameDisplay=nameDisplay;end

INSERT INTO `gibbonGovernmentDocumentType` (`name`, `nameDisplay`, `description`, `category`, `required`, `expiryRequired`, `expiryWarningDays`, `sequenceNumber`, `active`)
VALUES ('parent_id', 'Parent Government ID', 'Government-issued photo ID for parent/guardian', 'Parent', 'Y', 'Y', 30, 3, 'Y')
ON DUPLICATE KEY UPDATE nameDisplay=nameDisplay;end

INSERT INTO `gibbonGovernmentDocumentType` (`name`, `nameDisplay`, `description`, `category`, `required`, `expiryRequired`, `expiryWarningDays`, `sequenceNumber`, `active`)
VALUES ('parent_citizenship_proof', 'Parent Citizenship/Immigration Proof', 'Proof of citizenship or immigration status for parent/guardian', 'Parent', 'N', 'Y', 60, 4, 'Y')
ON DUPLICATE KEY UPDATE nameDisplay=nameDisplay;end

INSERT INTO `gibbonGovernmentDocumentType` (`name`, `nameDisplay`, `description`, `category`, `required`, `expiryRequired`, `expiryWarningDays`, `sequenceNumber`, `active`)
VALUES ('health_card', 'Health Insurance Card', 'Provincial health insurance card (RAMQ)', 'Child', 'Y', 'Y', 30, 5, 'Y')
ON DUPLICATE KEY UPDATE nameDisplay=nameDisplay;end

INSERT INTO `gibbonGovernmentDocumentType` (`name`, `nameDisplay`, `description`, `category`, `required`, `expiryRequired`, `expiryWarningDays`, `sequenceNumber`, `active`)
VALUES ('immunization_record', 'Immunization Record', 'Official immunization/vaccination record', 'Child', 'Y', 'N', NULL, 6, 'Y')
ON DUPLICATE KEY UPDATE nameDisplay=nameDisplay;end
";
