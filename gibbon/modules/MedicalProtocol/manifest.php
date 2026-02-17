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
$name        = 'Medical Protocol';
$description = 'Quebec-mandated medical protocols: Acetaminophen (FO-0647) and Insect Repellent (FO-0646) with weight-based dosing, authorization forms, e-signatures, administration logging, and compliance tracking.';
$entryURL    = 'medicalProtocol.php';
$type        = 'Additional';
$category    = 'Childcare';
$version     = '1.0.00';
$author      = 'LAYA';
$url         = 'https://laya.ca';

// Module tables & gibbonSetting entries
$moduleTables = [];

// Protocol definitions table - stores the two Quebec protocols and their dosing information
$moduleTables[] = "CREATE TABLE `gibbonMedicalProtocol` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Dosing table for Acetaminophen - weight-based doses per FO-0647
$moduleTables[] = "CREATE TABLE `gibbonMedicalProtocolDosing` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Parent authorizations with e-signatures and weight tracking
$moduleTables[] = "CREATE TABLE `gibbonMedicalProtocolAuthorization` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Administration log for tracking when protocols are administered
$moduleTables[] = "CREATE TABLE `gibbonMedicalProtocolAdministration` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// gibbonSetting entries
$gibbonSetting = [];

$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Medical Protocol', 'acetaminophenIntervalMinutes', 'Acetaminophen Minimum Interval', 'Minimum time in minutes between acetaminophen doses', '240');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Medical Protocol', 'acetaminophenMaxDailyDoses', 'Acetaminophen Max Daily Doses', 'Maximum number of acetaminophen doses per 24 hours', '5');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Medical Protocol', 'weightValidityMonths', 'Weight Validity Period', 'Number of months before weight must be re-validated', '3');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Medical Protocol', 'insectRepellentMinAgeMonths', 'Insect Repellent Minimum Age', 'Minimum age in months for insect repellent application', '6');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Medical Protocol', 'notifyParentOnAdministration', 'Notify Parent on Administration', 'Automatically notify parents when protocol is administered', 'Y');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Medical Protocol', 'requireFollowUpCheck', 'Require Follow-up Check', 'Require 60-minute follow-up check after acetaminophen', 'Y');";

// Seed data for the two Quebec protocols
$gibbonSetting[] = "INSERT INTO gibbonMedicalProtocol (name, formCode, type, description, legalText, dosingInstructions, ageRestrictionMonths, intervalMinutes, maxDailyDoses, requiresTemperature, active) VALUES
('Acetaminophen', 'FO-0647', 'Medication',
'Quebec-mandated protocol for acetaminophen administration to reduce fever in children. Follows 10-step administration procedure with temperature measurement, weight-based dosing, and 60-minute recheck.',
'I authorize the childcare facility to administer acetaminophen to my child according to the protocol FO-0647 established by the Ministère de la Famille du Québec. I confirm that my child has no known allergies or contraindications to acetaminophen. I understand that medication will only be administered when my child presents with a fever as defined by age-appropriate temperature thresholds.',
'{\"dosePerKg\":{\"min\":10,\"max\":15,\"unit\":\"mg\"},\"concentrations\":[\"80mg/mL\",\"80mg/5mL\",\"160mg/5mL\"]}',
NULL, 240, 5, 'Y', 'Y'),
('Insect Repellent', 'FO-0646', 'Topical',
'Quebec-mandated protocol for insect repellent application. DEET maximum 10%, Picaridin maximum 20%. Application restricted to exposed skin only, avoiding hands, eyes, mouth, and cuts.',
'I authorize the childcare facility to apply insect repellent to my child according to the protocol FO-0646 established by the Ministère de la Famille du Québec. I confirm that my child has no known allergies or sensitivities to DEET or Picaridin-based products. I understand that repellent will only be applied to exposed skin areas as needed for outdoor activities.',
'{\"maxDEET\":10,\"maxPicaridin\":20,\"prohibitedAreas\":[\"hands\",\"eyes\",\"mouth\",\"cuts\",\"irritated skin\"]}',
6, NULL, NULL, 'N', 'Y');";

// Seed acetaminophen dosing table per FO-0647 (weights 4.3kg to 35kg, 3 concentrations)
// 80mg/mL (drops) dosing
$gibbonSetting[] = "INSERT INTO gibbonMedicalProtocolDosing (gibbonMedicalProtocolID, weightMinKg, weightMaxKg, concentration, doseAmount, doseMg, notes) VALUES
(1, 4.30, 5.30, '80mg/mL', 0.5, 40, 'Drops'),
(1, 5.40, 7.90, '80mg/mL', 1.0, 80, 'Drops'),
(1, 8.00, 10.90, '80mg/mL', 1.5, 120, 'Drops'),
(1, 11.00, 15.90, '80mg/mL', 2.0, 160, 'Drops'),
(1, 16.00, 21.90, '80mg/mL', 2.5, 200, 'Drops'),
(1, 22.00, 26.90, '80mg/mL', 3.0, 240, 'Drops'),
(1, 27.00, 31.90, '80mg/mL', 3.5, 280, 'Drops'),
(1, 32.00, 35.00, '80mg/mL', 4.0, 320, 'Drops');";

// 80mg/5mL (syrup) dosing
$gibbonSetting[] = "INSERT INTO gibbonMedicalProtocolDosing (gibbonMedicalProtocolID, weightMinKg, weightMaxKg, concentration, doseAmount, doseMg, notes) VALUES
(1, 4.30, 5.30, '80mg/5mL', 2.5, 40, 'Syrup'),
(1, 5.40, 7.90, '80mg/5mL', 5.0, 80, 'Syrup'),
(1, 8.00, 10.90, '80mg/5mL', 7.5, 120, 'Syrup'),
(1, 11.00, 15.90, '80mg/5mL', 10.0, 160, 'Syrup'),
(1, 16.00, 21.90, '80mg/5mL', 12.5, 200, 'Syrup'),
(1, 22.00, 26.90, '80mg/5mL', 15.0, 240, 'Syrup'),
(1, 27.00, 31.90, '80mg/5mL', 17.5, 280, 'Syrup'),
(1, 32.00, 35.00, '80mg/5mL', 20.0, 320, 'Syrup');";

// 160mg/5mL (concentrated syrup) dosing
$gibbonSetting[] = "INSERT INTO gibbonMedicalProtocolDosing (gibbonMedicalProtocolID, weightMinKg, weightMaxKg, concentration, doseAmount, doseMg, notes) VALUES
(1, 4.30, 5.30, '160mg/5mL', 1.25, 40, 'Concentrated syrup'),
(1, 5.40, 7.90, '160mg/5mL', 2.5, 80, 'Concentrated syrup'),
(1, 8.00, 10.90, '160mg/5mL', 3.75, 120, 'Concentrated syrup'),
(1, 11.00, 15.90, '160mg/5mL', 5.0, 160, 'Concentrated syrup'),
(1, 16.00, 21.90, '160mg/5mL', 6.25, 200, 'Concentrated syrup'),
(1, 22.00, 26.90, '160mg/5mL', 7.5, 240, 'Concentrated syrup'),
(1, 27.00, 31.90, '160mg/5mL', 8.75, 280, 'Concentrated syrup'),
(1, 32.00, 35.00, '160mg/5mL', 10.0, 320, 'Concentrated syrup');";

// Action rows for gibbonAction
$actionRows = [];

$actionRows[] = [
    'name'                      => 'Medical Protocol Dashboard',
    'precedence'                => 0,
    'category'                  => 'Medical',
    'description'               => 'View medical protocol dashboard showing protocol summary, pending authorizations, and recent administrations.',
    'URLList'                   => 'medicalProtocol.php',
    'entryURL'                  => 'medicalProtocol.php',
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
    'name'                      => 'Manage Authorizations',
    'precedence'                => 0,
    'category'                  => 'Medical',
    'description'               => 'View and manage parent authorizations for medical protocols.',
    'URLList'                   => 'medicalProtocol_authorizations.php',
    'entryURL'                  => 'medicalProtocol_authorizations.php',
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
    'name'                      => 'Administer Protocol',
    'precedence'                => 0,
    'category'                  => 'Medical',
    'description'               => 'Log administration of medical protocols (acetaminophen or insect repellent) to authorized children.',
    'URLList'                   => 'medicalProtocol_administer.php',
    'entryURL'                  => 'medicalProtocol_administer.php',
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
    'name'                      => 'Administration Log',
    'precedence'                => 0,
    'category'                  => 'Medical',
    'description'               => 'View history of all medical protocol administrations.',
    'URLList'                   => 'medicalProtocol_log.php',
    'entryURL'                  => 'medicalProtocol_log.php',
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
    'name'                      => 'Compliance Reports',
    'precedence'                => 0,
    'category'                  => 'Medical',
    'description'               => 'View and export Quebec compliance reports for medical protocols (FO-0647, FO-0646).',
    'URLList'                   => 'medicalProtocol_compliance.php',
    'entryURL'                  => 'medicalProtocol_compliance.php',
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
