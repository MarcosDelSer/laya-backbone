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

// RL-24 Submission Module - Database Change Log
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = array();
$count = 0;

// v1.0.00 - Initial release
++$count;
$sql[$count][0] = '1.0.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonRL24Transmission` (
    `gibbonRL24TransmissionID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `taxYear` SMALLINT UNSIGNED NOT NULL COMMENT 'Calendar year for tax reporting (e.g., 2024)',
    `sequenceNumber` SMALLINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Sequence within the year (SSS in filename)',
    `fileName` VARCHAR(50) NULL COMMENT 'Generated filename format: AAPPPPPPSSS.xml',
    `status` ENUM('Draft','Generated','Validated','Submitted','Accepted','Rejected','Cancelled') NOT NULL DEFAULT 'Draft',
    `preparerNumber` VARCHAR(20) NULL COMMENT 'Revenu Quebec preparer ID',
    `providerName` VARCHAR(100) NULL,
    `providerNEQ` VARCHAR(20) NULL COMMENT 'Quebec Enterprise Number (NEQ)',
    `providerAddress` TEXT NULL,
    `totalSlips` INT UNSIGNED NOT NULL DEFAULT 0,
    `totalAmountCase11` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Total childcare expenses (Box 11)',
    `totalAmountCase12` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Total eligible expenses (Box 12)',
    `totalDays` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total days across all slips',
    `xmlFilePath` VARCHAR(255) NULL COMMENT 'Path to generated XML file',
    `xmlValidated` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `xmlValidationErrors` TEXT NULL,
    `submissionDate` DATE NULL,
    `confirmationNumber` VARCHAR(50) NULL COMMENT 'Government confirmation number',
    `rejectionReason` TEXT NULL,
    `notes` TEXT NULL,
    `generatedByID` INT UNSIGNED NULL COMMENT 'Staff who generated the transmission',
    `submittedByID` INT UNSIGNED NULL COMMENT 'Staff who submitted the transmission',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonSchoolYearID` (`gibbonSchoolYearID`),
    KEY `taxYear` (`taxYear`),
    KEY `status` (`status`),
    UNIQUE KEY `taxYearSequence` (`taxYear`, `sequenceNumber`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonRL24Slip` (
    `gibbonRL24SlipID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonRL24TransmissionID` INT UNSIGNED NOT NULL,
    `gibbonPersonIDChild` INT UNSIGNED NOT NULL COMMENT 'Child who received childcare',
    `gibbonPersonIDParent` INT UNSIGNED NULL COMMENT 'Parent/guardian claiming the credit',
    `slipNumber` INT UNSIGNED NOT NULL COMMENT 'Sequential slip number within transmission',
    `taxYear` SMALLINT UNSIGNED NOT NULL,
    `parentFirstName` VARCHAR(60) NOT NULL,
    `parentLastName` VARCHAR(60) NOT NULL,
    `parentSIN` VARCHAR(11) NULL COMMENT 'Encrypted SIN (format: XXX XXX XXX)',
    `parentAddressLine1` VARCHAR(100) NULL,
    `parentAddressLine2` VARCHAR(100) NULL,
    `parentCity` VARCHAR(60) NULL,
    `parentProvince` CHAR(2) NULL DEFAULT 'QC',
    `parentPostalCode` VARCHAR(10) NULL,
    `childFirstName` VARCHAR(60) NOT NULL,
    `childLastName` VARCHAR(60) NOT NULL,
    `childDateOfBirth` DATE NULL,
    `servicePeriodStart` DATE NOT NULL,
    `servicePeriodEnd` DATE NOT NULL,
    `totalDays` SMALLINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total days of childcare (Box 10)',
    `case11Amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Childcare expenses paid (Box 11)',
    `case12Amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Eligible childcare expenses (Box 12)',
    `case13Amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Government contributions received',
    `case14Amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Net eligible expenses',
    `caseACode` CHAR(1) NULL COMMENT 'Slip type code (O=Original, A=Amended, D=Cancelled)',
    `caseBCode` VARCHAR(10) NULL COMMENT 'Additional code if applicable',
    `status` ENUM('Draft','Included','Amended','Cancelled') NOT NULL DEFAULT 'Draft',
    `amendedSlipID` INT UNSIGNED NULL COMMENT 'Reference to original slip if this is an amendment',
    `notes` TEXT NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonRL24TransmissionID` (`gibbonRL24TransmissionID`),
    KEY `gibbonPersonIDChild` (`gibbonPersonIDChild`),
    KEY `gibbonPersonIDParent` (`gibbonPersonIDParent`),
    KEY `taxYear` (`taxYear`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonRL24Eligibility` (
    `gibbonRL24EligibilityID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `gibbonPersonIDChild` INT UNSIGNED NOT NULL COMMENT 'Child enrolled in childcare',
    `gibbonPersonIDParent` INT UNSIGNED NULL COMMENT 'Parent/guardian completing the form',
    `formYear` SMALLINT UNSIGNED NOT NULL COMMENT 'Tax year for eligibility (FO-0601)',
    `parentFirstName` VARCHAR(60) NOT NULL,
    `parentLastName` VARCHAR(60) NOT NULL,
    `parentSIN` VARCHAR(11) NULL COMMENT 'Encrypted SIN',
    `parentAddressLine1` VARCHAR(100) NULL,
    `parentAddressLine2` VARCHAR(100) NULL,
    `parentCity` VARCHAR(60) NULL,
    `parentProvince` CHAR(2) NULL DEFAULT 'QC',
    `parentPostalCode` VARCHAR(10) NULL,
    `parentPhone` VARCHAR(20) NULL,
    `parentEmail` VARCHAR(100) NULL,
    `citizenshipStatus` ENUM('Canadian','PermanentResident','Refugee','Other') NULL,
    `citizenshipOther` VARCHAR(100) NULL COMMENT 'Description if Other selected',
    `residencyStatus` ENUM('Quebec','OutOfProvince') NULL DEFAULT 'Quebec',
    `childFirstName` VARCHAR(60) NOT NULL,
    `childLastName` VARCHAR(60) NOT NULL,
    `childDateOfBirth` DATE NULL,
    `childRelationship` VARCHAR(50) NULL COMMENT 'Relationship to parent (e.g., Son, Daughter)',
    `servicePeriodStart` DATE NULL,
    `servicePeriodEnd` DATE NULL,
    `divisionNumber` VARCHAR(20) NULL COMMENT 'Provider division/permit number',
    `approvalStatus` ENUM('Pending','Approved','Rejected','Incomplete') NOT NULL DEFAULT 'Pending',
    `approvalDate` DATE NULL,
    `approvalNotes` TEXT NULL,
    `approvedByID` INT UNSIGNED NULL COMMENT 'Staff who approved the form',
    `documentsComplete` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `signatureDate` DATE NULL COMMENT 'Date parent signed the form',
    `signatureConfirmed` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `notes` TEXT NULL,
    `createdByID` INT UNSIGNED NOT NULL COMMENT 'Staff who created the record',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonSchoolYearID` (`gibbonSchoolYearID`),
    KEY `gibbonPersonIDChild` (`gibbonPersonIDChild`),
    KEY `gibbonPersonIDParent` (`gibbonPersonIDParent`),
    KEY `formYear` (`formYear`),
    KEY `approvalStatus` (`approvalStatus`),
    UNIQUE KEY `childFormYear` (`gibbonPersonIDChild`, `formYear`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonRL24EligibilityDocument` (
    `gibbonRL24EligibilityDocumentID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonRL24EligibilityID` INT UNSIGNED NOT NULL,
    `documentType` ENUM('ProofOfCitizenship','ProofOfResidency','BirthCertificate','SINDocument','ProofOfGuardianship','Other') NOT NULL,
    `documentName` VARCHAR(100) NOT NULL,
    `filePath` VARCHAR(255) NOT NULL,
    `fileType` VARCHAR(50) NULL COMMENT 'MIME type of the uploaded file',
    `fileSize` INT UNSIGNED NULL COMMENT 'File size in bytes',
    `verificationStatus` ENUM('Pending','Verified','Rejected') NOT NULL DEFAULT 'Pending',
    `verificationNotes` TEXT NULL,
    `verifiedByID` INT UNSIGNED NULL COMMENT 'Staff who verified the document',
    `verifiedDate` DATE NULL,
    `uploadedByID` INT UNSIGNED NOT NULL COMMENT 'User who uploaded the document',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonRL24EligibilityID` (`gibbonRL24EligibilityID`),
    KEY `documentType` (`documentType`),
    KEY `verificationStatus` (`verificationStatus`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('RL-24 Submission', 'preparerNumber', 'Preparer Number', 'Revenu Quebec preparer identification number', '') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('RL-24 Submission', 'providerName', 'Provider Name', 'Childcare provider official name for RL-24 forms', '') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('RL-24 Submission', 'providerNEQ', 'Provider NEQ', 'Quebec Enterprise Number (NEQ) for the provider', '') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('RL-24 Submission', 'providerAddress', 'Provider Address', 'Official address for RL-24 forms', '') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('RL-24 Submission', 'xmlOutputPath', 'XML Output Path', 'Directory path for storing generated XML files', 'uploads/rl24/') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('RL-24 Submission', 'autoCalculateDays', 'Auto-calculate Days', 'Automatically calculate attendance days from CareTracking data', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('RL-24 Submission', 'requireSINValidation', 'Require SIN Validation', 'Validate SIN format before generating slips', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('RL-24 Submission', 'documentRetentionYears', 'Document Retention Years', 'Number of years to retain eligibility documents', '7') ON DUPLICATE KEY UPDATE scope=scope;end
";
