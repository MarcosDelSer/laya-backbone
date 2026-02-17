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
$name        = 'Service Agreement';
$description = 'Digital Quebec FO-0659 Service Agreement (Entente de Services) with all 13 articles, annexes A-D, e-signatures, and PDF generation.';
$entryURL    = 'serviceAgreement.php';
$type        = 'Additional';
$category    = 'Childcare';
$version     = '1.0.00';
$author      = 'LAYA';
$url         = 'https://laya.ca';

// Module tables & gibbonSetting entries
$moduleTables = [];

// Main Service Agreement table - stores Quebec FO-0659 form data
$moduleTables[] = "CREATE TABLE `gibbonServiceAgreement` (
    `gibbonServiceAgreementID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `gibbonPersonIDChild` INT UNSIGNED NOT NULL COMMENT 'Child receiving services',
    `gibbonPersonIDParent` INT UNSIGNED NOT NULL COMMENT 'Parent/guardian signing agreement',
    `agreementNumber` VARCHAR(50) NULL COMMENT 'Agreement reference number',
    `status` ENUM('Draft','Pending Signature','Active','Expired','Terminated','Cancelled') NOT NULL DEFAULT 'Draft',

    -- Article 1: Identification of Parties
    `providerName` VARCHAR(100) NOT NULL COMMENT 'Childcare provider name',
    `providerPermitNumber` VARCHAR(50) NULL COMMENT 'Provider permit number from MFA',
    `providerAddress` VARCHAR(255) NULL,
    `providerPhone` VARCHAR(30) NULL,
    `providerEmail` VARCHAR(100) NULL,
    `parentName` VARCHAR(100) NOT NULL COMMENT 'Parent/guardian legal name',
    `parentAddress` VARCHAR(255) NULL,
    `parentPhone` VARCHAR(30) NULL,
    `parentEmail` VARCHAR(100) NULL,
    `childName` VARCHAR(100) NOT NULL COMMENT 'Child legal name',
    `childDateOfBirth` DATE NOT NULL,

    -- Article 2: Description of Services
    `maxHoursPerDay` DECIMAL(4,2) NOT NULL DEFAULT 10.00 COMMENT 'Max hours per day (usually 10)',
    `includesBreakfast` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `includesLunch` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `includesSnacks` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `includesDinner` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `serviceDescription` TEXT NULL COMMENT 'Additional service description',

    -- Article 3: Operating Hours
    `operatingHoursStart` TIME NOT NULL DEFAULT '07:00:00',
    `operatingHoursEnd` TIME NOT NULL DEFAULT '18:00:00',
    `operatingDays` VARCHAR(50) NOT NULL DEFAULT 'Mon,Tue,Wed,Thu,Fri' COMMENT 'Comma-separated operating days',

    -- Article 4: Attendance Pattern
    `attendancePattern` TEXT NOT NULL COMMENT 'JSON: scheduled days and hours per week',
    `hoursPerWeek` DECIMAL(5,2) NULL COMMENT 'Total scheduled hours per week',

    -- Article 5: Payment Terms (Reduced Contribution)
    `contributionType` ENUM('Reduced','Full','Mixed') NOT NULL DEFAULT 'Reduced',
    `dailyReducedContribution` DECIMAL(8,2) NOT NULL DEFAULT 9.35 COMMENT 'Quebec reduced contribution rate',
    `additionalDailyRate` DECIMAL(8,2) NULL COMMENT 'Additional amount beyond reduced contribution',
    `paymentFrequency` ENUM('Daily','Weekly','Biweekly','Monthly') NOT NULL DEFAULT 'Monthly',
    `paymentMethod` ENUM('DirectDebit','BankTransfer','Cheque','Cash','Other') NOT NULL DEFAULT 'DirectDebit',
    `paymentDueDay` INT UNSIGNED NULL COMMENT 'Day of month payment is due',

    -- Article 6: Late Pickup Fees
    `latePickupFeePerMinute` DECIMAL(6,2) NULL DEFAULT 1.00,
    `latePickupGracePeriod` INT UNSIGNED NULL DEFAULT 10 COMMENT 'Grace period in minutes',
    `latePickupMaxFee` DECIMAL(8,2) NULL,

    -- Article 7: Closure Days
    `statutoryHolidaysClosed` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `summerClosureWeeks` INT UNSIGNED NULL DEFAULT 2 COMMENT 'Number of summer closure weeks',
    `winterClosureWeeks` INT UNSIGNED NULL DEFAULT 1 COMMENT 'Number of winter closure weeks',
    `closureDatesText` TEXT NULL COMMENT 'Specific closure dates description',

    -- Article 8: Absence Policy
    `maxAbsenceDaysPerYear` INT UNSIGNED NULL,
    `absenceNoticeRequired` INT UNSIGNED NULL DEFAULT 24 COMMENT 'Hours notice required for absence',
    `absenceChargePolicy` ENUM('ChargeAll','ChargePartial','NoCharge') NOT NULL DEFAULT 'ChargeAll',
    `medicalAbsencePolicy` TEXT NULL,

    -- Article 9: Agreement Duration
    `effectiveDate` DATE NOT NULL,
    `expirationDate` DATE NULL COMMENT 'NULL for indefinite agreements',
    `renewalType` ENUM('AutoRenew','RequiresRenewal','FixedTerm') NOT NULL DEFAULT 'AutoRenew',
    `renewalNoticeRequired` INT UNSIGNED NULL DEFAULT 30 COMMENT 'Days notice for renewal decisions',

    -- Article 10: Termination Conditions
    `parentTerminationNotice` INT UNSIGNED NOT NULL DEFAULT 14 COMMENT 'Days notice required from parent',
    `providerTerminationNotice` INT UNSIGNED NOT NULL DEFAULT 14 COMMENT 'Days notice required from provider',
    `immediateTerminationConditions` TEXT NULL,
    `terminationRefundPolicy` TEXT NULL,

    -- Article 11: Special Conditions
    `specialConditions` TEXT NULL COMMENT 'Any special conditions agreed upon',

    -- Article 12: Consumer Protection Act
    `consumerProtectionAcknowledged` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `consumerProtectionAcknowledgedDate` DATETIME NULL,

    -- Article 13: Signatures (reference to signature table)
    `allSignaturesComplete` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `agreementCompletedDate` DATETIME NULL,

    -- Metadata
    `createdByID` INT UNSIGNED NOT NULL COMMENT 'Staff who created agreement',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY `gibbonPersonIDChild` (`gibbonPersonIDChild`),
    KEY `gibbonPersonIDParent` (`gibbonPersonIDParent`),
    KEY `gibbonSchoolYearID` (`gibbonSchoolYearID`),
    KEY `status` (`status`),
    KEY `effectiveDate` (`effectiveDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Annex table - stores A, B, C, D annexes for each agreement
$moduleTables[] = "CREATE TABLE `gibbonServiceAgreementAnnex` (
    `gibbonServiceAgreementAnnexID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonServiceAgreementID` INT UNSIGNED NOT NULL,
    `annexType` ENUM('A','B','C','D') NOT NULL COMMENT 'A=Field Trips, B=Hygiene Items, C=Supplementary Meals, D=Extended Hours',
    `status` ENUM('NotApplicable','Pending','Signed','Declined') NOT NULL DEFAULT 'NotApplicable',

    -- Annex A: Field Trips Authorization
    `fieldTripsAuthorized` ENUM('Y','N') NULL,
    `fieldTripsConditions` TEXT NULL,

    -- Annex B: Hygiene Items
    `hygieneItemsIncluded` ENUM('Y','N') NULL,
    `hygieneItemsDescription` TEXT NULL COMMENT 'List of hygiene items provided',
    `hygieneItemsMonthlyFee` DECIMAL(8,2) NULL,

    -- Annex C: Supplementary Meals
    `supplementaryMealsIncluded` ENUM('Y','N') NULL,
    `supplementaryMealsDays` VARCHAR(50) NULL COMMENT 'Days when supplementary meals apply',
    `supplementaryMealsDescription` TEXT NULL,
    `supplementaryMealsFee` DECIMAL(8,2) NULL,

    -- Annex D: Extended Hours
    `extendedHoursIncluded` ENUM('Y','N') NULL,
    `extendedHoursStart` TIME NULL,
    `extendedHoursEnd` TIME NULL,
    `extendedHoursHourlyRate` DECIMAL(8,2) NULL,
    `extendedHoursMaxDaily` DECIMAL(4,2) NULL COMMENT 'Max extended hours per day',

    -- Metadata
    `signedDate` DATETIME NULL,
    `signedByID` INT UNSIGNED NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY `gibbonServiceAgreementID` (`gibbonServiceAgreementID`),
    KEY `annexType` (`annexType`),
    CONSTRAINT `fk_annexAgreement` FOREIGN KEY (`gibbonServiceAgreementID`)
        REFERENCES `gibbonServiceAgreement`(`gibbonServiceAgreementID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Signature table - stores electronic signatures with audit trail
$moduleTables[] = "CREATE TABLE `gibbonServiceAgreementSignature` (
    `gibbonServiceAgreementSignatureID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonServiceAgreementID` INT UNSIGNED NOT NULL,
    `signerType` ENUM('Parent','Provider','Witness') NOT NULL,
    `gibbonPersonID` INT UNSIGNED NULL COMMENT 'Person who signed (if in system)',
    `signerName` VARCHAR(100) NOT NULL COMMENT 'Legal name of signer',
    `signerEmail` VARCHAR(100) NULL,

    -- Signature Data
    `signatureData` MEDIUMTEXT NOT NULL COMMENT 'Base64 encoded signature image or SVG path',
    `signatureType` ENUM('Drawn','Typed','Image') NOT NULL DEFAULT 'Drawn',

    -- Legal Acknowledgments
    `legalAcknowledgment` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Acknowledged legal binding',
    `consumerProtectionAcknowledged` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Acknowledged Consumer Protection Act notice',
    `termsAccepted` ENUM('Y','N') NOT NULL DEFAULT 'N',

    -- Audit Trail
    `signedDate` DATETIME NOT NULL,
    `ipAddress` VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6 address',
    `userAgent` VARCHAR(500) NULL COMMENT 'Browser/device information',
    `sessionID` VARCHAR(100) NULL COMMENT 'Session identifier for verification',

    -- Verification
    `verificationHash` VARCHAR(64) NULL COMMENT 'SHA-256 hash of agreement at signing time',
    `verified` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `verifiedDate` DATETIME NULL,
    `verifiedByID` INT UNSIGNED NULL,

    -- Metadata
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    KEY `gibbonServiceAgreementID` (`gibbonServiceAgreementID`),
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `signerType` (`signerType`),
    KEY `signedDate` (`signedDate`),
    CONSTRAINT `fk_signatureAgreement` FOREIGN KEY (`gibbonServiceAgreementID`)
        REFERENCES `gibbonServiceAgreement`(`gibbonServiceAgreementID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Payment table - stores payment terms and history
$moduleTables[] = "CREATE TABLE `gibbonServiceAgreementPayment` (
    `gibbonServiceAgreementPaymentID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonServiceAgreementID` INT UNSIGNED NOT NULL,

    -- Payment Schedule
    `periodStart` DATE NOT NULL,
    `periodEnd` DATE NOT NULL,
    `dueDate` DATE NOT NULL,

    -- Amounts
    `reducedContributionAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `additionalServicesAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Meals, extended hours, etc.',
    `latePickupFees` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `otherFees` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `adjustments` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Credits or adjustments',
    `totalAmount` DECIMAL(10,2) NOT NULL,

    -- Tax Credit Information (Quebec specific)
    `eligibleForTaxCredit` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `taxCreditAmount` DECIMAL(10,2) NULL,

    -- Payment Status
    `status` ENUM('Scheduled','Invoiced','Paid','PartiallyPaid','Overdue','Cancelled','Refunded') NOT NULL DEFAULT 'Scheduled',
    `paidDate` DATE NULL,
    `paidAmount` DECIMAL(10,2) NULL,
    `paymentReference` VARCHAR(100) NULL COMMENT 'Transaction reference number',

    -- Notes
    `description` TEXT NULL,
    `notes` TEXT NULL,

    -- Metadata
    `createdByID` INT UNSIGNED NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY `gibbonServiceAgreementID` (`gibbonServiceAgreementID`),
    KEY `periodStart` (`periodStart`),
    KEY `dueDate` (`dueDate`),
    KEY `status` (`status`),
    CONSTRAINT `fk_paymentAgreement` FOREIGN KEY (`gibbonServiceAgreementID`)
        REFERENCES `gibbonServiceAgreement`(`gibbonServiceAgreementID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// gibbonSetting entries
$gibbonSetting = [];

$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Service Agreement', 'defaultReducedContribution', 'Default Reduced Contribution', 'Quebec reduced contribution daily rate', '9.35');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Service Agreement', 'defaultMaxHours', 'Default Max Hours Per Day', 'Maximum hours per day for childcare services', '10');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Service Agreement', 'latePickupFeePerMinute', 'Late Pickup Fee Per Minute', 'Fee charged per minute for late pickup', '1.00');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Service Agreement', 'latePickupGracePeriod', 'Late Pickup Grace Period', 'Grace period in minutes before late fees apply', '10');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Service Agreement', 'defaultTerminationNotice', 'Default Termination Notice', 'Default days notice required for termination', '14');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Service Agreement', 'requireConsumerProtectionAck', 'Require Consumer Protection Acknowledgment', 'Require parents to acknowledge Consumer Protection Act notice', 'Y');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Service Agreement', 'signatureType', 'Signature Type', 'Type of signature allowed (Drawn, Typed, Both)', 'Both');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Service Agreement', 'pdfTemplate', 'PDF Template', 'Template for generating signed agreement PDFs', 'quebec_fo0659');";

// Action rows for gibbonAction
$actionRows = [];

$actionRows[] = [
    'name'                      => 'Service Agreement Dashboard',
    'precedence'                => 0,
    'category'                  => 'Agreements',
    'description'               => 'View and manage all service agreements.',
    'URLList'                   => 'serviceAgreement.php',
    'entryURL'                  => 'serviceAgreement.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Create Service Agreement',
    'precedence'                => 0,
    'category'                  => 'Agreements',
    'description'               => 'Create a new service agreement for a child.',
    'URLList'                   => 'serviceAgreement_add.php',
    'entryURL'                  => 'serviceAgreement_add.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'View Service Agreement',
    'precedence'                => 0,
    'category'                  => 'Agreements',
    'description'               => 'View details of a service agreement including all articles and annexes.',
    'URLList'                   => 'serviceAgreement_view.php',
    'entryURL'                  => 'serviceAgreement_view.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'N',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'Y',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Sign Service Agreement',
    'precedence'                => 0,
    'category'                  => 'Agreements',
    'description'               => 'Electronically sign a service agreement.',
    'URLList'                   => 'serviceAgreement_sign.php',
    'entryURL'                  => 'serviceAgreement_sign.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'N',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'Y',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Generate Agreement PDF',
    'precedence'                => 0,
    'category'                  => 'Agreements',
    'description'               => 'Generate PDF of a signed service agreement.',
    'URLList'                   => 'serviceAgreement_pdf.php',
    'entryURL'                  => 'serviceAgreement_pdf.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'N',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'Y',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Service Agreement Settings',
    'precedence'                => 0,
    'category'                  => 'Agreements',
    'description'               => 'Configure service agreement default settings and templates.',
    'URLList'                   => 'serviceAgreement_settings.php',
    'entryURL'                  => 'serviceAgreement_settings.php',
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
