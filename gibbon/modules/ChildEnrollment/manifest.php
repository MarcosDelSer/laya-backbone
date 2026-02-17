<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This is a Gibbon module for the LAYA childcare management platform.
*/

// This file describes the module, including database tables

// Basic variables
$name        = 'Child Enrollment';
$description = 'Quebec-compliant digital enrollment form (Fiche d\'Inscription) with comprehensive child/parent information, health and nutrition sections, e-signature capture, document versioning, and PDF export.';
$entryURL    = 'enrollment_list.php';
$type        = 'Additional';
$category    = 'Childcare';
$version     = '1.0.00';
$author      = 'LAYA';
$url         = 'https://laya.ca';

// Module tables & gibbonSetting entries
$moduleTables = [];

// Main enrollment form table
$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonChildEnrollmentForm` (
    `gibbonChildEnrollmentFormID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Child being enrolled',
    `gibbonFamilyID` INT UNSIGNED NOT NULL COMMENT 'Family of the child',
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `formNumber` VARCHAR(50) NOT NULL UNIQUE,
    `status` ENUM('Draft','Submitted','Approved','Rejected','Expired') NOT NULL DEFAULT 'Draft',
    `version` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Document version number',
    `admissionDate` DATE NULL COMMENT 'Expected admission date',
    `childFirstName` VARCHAR(100) NOT NULL,
    `childLastName` VARCHAR(100) NOT NULL,
    `childDateOfBirth` DATE NOT NULL,
    `childAddress` VARCHAR(255) NULL,
    `childCity` VARCHAR(100) NULL,
    `childPostalCode` VARCHAR(20) NULL,
    `languagesSpoken` VARCHAR(255) NULL COMMENT 'Comma-separated list of languages',
    `notes` TEXT NULL,
    `submittedAt` DATETIME NULL,
    `approvedAt` DATETIME NULL,
    `approvedByID` INT UNSIGNED NULL COMMENT 'Staff who approved',
    `rejectedAt` DATETIME NULL,
    `rejectedByID` INT UNSIGNED NULL COMMENT 'Staff who rejected',
    `rejectionReason` TEXT NULL,
    `createdByID` INT UNSIGNED NOT NULL COMMENT 'User who created the form',
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_person` (`gibbonPersonID`),
    INDEX `idx_family` (`gibbonFamilyID`),
    INDEX `idx_school_year` (`gibbonSchoolYearID`),
    INDEX `idx_status` (`status`),
    INDEX `idx_admission_date` (`admissionDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Parent/Guardian information table
$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonChildEnrollmentParent` (
    `gibbonChildEnrollmentParentID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonChildEnrollmentFormID` INT UNSIGNED NOT NULL,
    `parentNumber` ENUM('1','2') NOT NULL COMMENT 'Parent 1 or Parent 2',
    `name` VARCHAR(150) NOT NULL,
    `relationship` VARCHAR(50) NOT NULL COMMENT 'Mother, Father, Guardian, etc.',
    `address` VARCHAR(255) NULL,
    `city` VARCHAR(100) NULL,
    `postalCode` VARCHAR(20) NULL,
    `homePhone` VARCHAR(30) NULL,
    `cellPhone` VARCHAR(30) NULL,
    `workPhone` VARCHAR(30) NULL,
    `email` VARCHAR(150) NULL,
    `employer` VARCHAR(150) NULL,
    `workAddress` VARCHAR(255) NULL,
    `workHours` VARCHAR(100) NULL COMMENT 'e.g., 9AM-5PM',
    `isPrimaryContact` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_form` (`gibbonChildEnrollmentFormID`),
    INDEX `idx_parent_number` (`parentNumber`),
    CONSTRAINT `fk_parent_form` FOREIGN KEY (`gibbonChildEnrollmentFormID`)
        REFERENCES `gibbonChildEnrollmentForm`(`gibbonChildEnrollmentFormID`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Authorized pickup persons table
$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonChildEnrollmentAuthorizedPickup` (
    `gibbonChildEnrollmentAuthorizedPickupID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonChildEnrollmentFormID` INT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `relationship` VARCHAR(50) NOT NULL,
    `phone` VARCHAR(30) NOT NULL,
    `photoPath` VARCHAR(255) NULL COMMENT 'Path to uploaded photo',
    `priority` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Order of priority',
    `notes` TEXT NULL,
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_form` (`gibbonChildEnrollmentFormID`),
    INDEX `idx_priority` (`priority`),
    CONSTRAINT `fk_pickup_form` FOREIGN KEY (`gibbonChildEnrollmentFormID`)
        REFERENCES `gibbonChildEnrollmentForm`(`gibbonChildEnrollmentFormID`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Emergency contacts table
$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonChildEnrollmentEmergencyContact` (
    `gibbonChildEnrollmentEmergencyContactID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonChildEnrollmentFormID` INT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `relationship` VARCHAR(50) NOT NULL,
    `phone` VARCHAR(30) NOT NULL,
    `alternatePhone` VARCHAR(30) NULL,
    `priority` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Order of priority for contact',
    `notes` TEXT NULL,
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_form` (`gibbonChildEnrollmentFormID`),
    INDEX `idx_priority` (`priority`),
    CONSTRAINT `fk_emergency_form` FOREIGN KEY (`gibbonChildEnrollmentFormID`)
        REFERENCES `gibbonChildEnrollmentForm`(`gibbonChildEnrollmentFormID`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Health information table
$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonChildEnrollmentHealth` (
    `gibbonChildEnrollmentHealthID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonChildEnrollmentFormID` INT UNSIGNED NOT NULL,
    `allergies` TEXT NULL COMMENT 'JSON array of allergies with details',
    `medicalConditions` TEXT NULL COMMENT 'Description of medical conditions',
    `hasEpiPen` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `epiPenInstructions` TEXT NULL COMMENT 'Instructions for EpiPen use',
    `medications` TEXT NULL COMMENT 'JSON array of medications with dosage and schedule',
    `doctorName` VARCHAR(150) NULL,
    `doctorPhone` VARCHAR(30) NULL,
    `doctorAddress` VARCHAR(255) NULL,
    `healthInsuranceNumber` VARCHAR(50) NULL COMMENT 'Quebec RAMQ number',
    `healthInsuranceExpiry` DATE NULL,
    `specialNeeds` TEXT NULL COMMENT 'Description of special needs',
    `developmentalNotes` TEXT NULL COMMENT 'Developmental considerations',
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_form_unique` (`gibbonChildEnrollmentFormID`),
    CONSTRAINT `fk_health_form` FOREIGN KEY (`gibbonChildEnrollmentFormID`)
        REFERENCES `gibbonChildEnrollmentForm`(`gibbonChildEnrollmentFormID`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Nutrition information table
$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonChildEnrollmentNutrition` (
    `gibbonChildEnrollmentNutritionID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonChildEnrollmentFormID` INT UNSIGNED NOT NULL,
    `dietaryRestrictions` TEXT NULL COMMENT 'Dietary restrictions (religious, cultural, etc.)',
    `foodAllergies` TEXT NULL COMMENT 'Food allergies (separate from medical allergies)',
    `feedingInstructions` TEXT NULL COMMENT 'Special feeding instructions',
    `isBottleFeeding` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `bottleFeedingInfo` TEXT NULL COMMENT 'Bottle feeding details (formula type, schedule)',
    `foodPreferences` TEXT NULL COMMENT 'Foods the child likes',
    `foodDislikes` TEXT NULL COMMENT 'Foods the child dislikes',
    `mealPlanNotes` TEXT NULL,
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_form_unique` (`gibbonChildEnrollmentFormID`),
    CONSTRAINT `fk_nutrition_form` FOREIGN KEY (`gibbonChildEnrollmentFormID`)
        REFERENCES `gibbonChildEnrollmentForm`(`gibbonChildEnrollmentFormID`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Attendance pattern table (weekly schedule)
$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonChildEnrollmentAttendance` (
    `gibbonChildEnrollmentAttendanceID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonChildEnrollmentFormID` INT UNSIGNED NOT NULL,
    `mondayAm` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `mondayPm` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `tuesdayAm` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `tuesdayPm` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `wednesdayAm` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `wednesdayPm` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `thursdayAm` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `thursdayPm` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `fridayAm` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `fridayPm` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `saturdayAm` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `saturdayPm` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `sundayAm` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `sundayPm` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `expectedHoursPerWeek` DECIMAL(4,1) NULL COMMENT 'Expected hours per week',
    `expectedArrivalTime` TIME NULL,
    `expectedDepartureTime` TIME NULL,
    `notes` TEXT NULL,
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_form_unique` (`gibbonChildEnrollmentFormID`),
    CONSTRAINT `fk_attendance_form` FOREIGN KEY (`gibbonChildEnrollmentFormID`)
        REFERENCES `gibbonChildEnrollmentForm`(`gibbonChildEnrollmentFormID`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// E-signature table
$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonChildEnrollmentSignature` (
    `gibbonChildEnrollmentSignatureID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonChildEnrollmentFormID` INT UNSIGNED NOT NULL,
    `signatureType` ENUM('Parent1','Parent2','Director') NOT NULL,
    `signatureData` MEDIUMTEXT NOT NULL COMMENT 'Base64-encoded signature image (SVG or PNG)',
    `signerName` VARCHAR(150) NOT NULL COMMENT 'Name of person who signed',
    `signedAt` DATETIME NOT NULL,
    `ipAddress` VARCHAR(45) NULL COMMENT 'IP address at time of signing',
    `userAgent` VARCHAR(255) NULL COMMENT 'Browser/device info',
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_form` (`gibbonChildEnrollmentFormID`),
    INDEX `idx_signature_type` (`signatureType`),
    INDEX `idx_signed_at` (`signedAt`),
    UNIQUE KEY `idx_form_type` (`gibbonChildEnrollmentFormID`, `signatureType`),
    CONSTRAINT `fk_signature_form` FOREIGN KEY (`gibbonChildEnrollmentFormID`)
        REFERENCES `gibbonChildEnrollmentForm`(`gibbonChildEnrollmentFormID`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Audit trail table
$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonChildEnrollmentAudit` (
    `gibbonChildEnrollmentAuditID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonChildEnrollmentFormID` INT UNSIGNED NOT NULL,
    `action` ENUM('Created','Updated','StatusChange','Signed','Viewed','Exported','Deleted') NOT NULL,
    `fieldName` VARCHAR(100) NULL COMMENT 'Field that was changed (for updates)',
    `oldValue` TEXT NULL COMMENT 'Previous value',
    `newValue` TEXT NULL COMMENT 'New value',
    `performedByID` INT UNSIGNED NOT NULL COMMENT 'User who performed the action',
    `ipAddress` VARCHAR(45) NULL,
    `userAgent` VARCHAR(255) NULL,
    `notes` TEXT NULL,
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_form` (`gibbonChildEnrollmentFormID`),
    INDEX `idx_action` (`action`),
    INDEX `idx_performed_by` (`performedByID`),
    INDEX `idx_timestamp` (`timestampCreated`),
    CONSTRAINT `fk_audit_form` FOREIGN KEY (`gibbonChildEnrollmentFormID`)
        REFERENCES `gibbonChildEnrollmentForm`(`gibbonChildEnrollmentFormID`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Module Settings
$gibbonSetting = [];

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Child Enrollment', 'formNumberPrefix', 'Form Number Prefix', 'Prefix for generated form numbers (e.g., ENR-)', 'ENR-')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Child Enrollment', 'requireBothParentSignatures', 'Require Both Parent Signatures', 'Require signatures from both parents (Y/N)', 'N')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Child Enrollment', 'requireDirectorSignature', 'Require Director Signature', 'Require director signature for approval (Y/N)', 'Y')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Child Enrollment', 'formExpiryDays', 'Form Expiry Days', 'Number of days before a draft form expires', '30')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Child Enrollment', 'minEmergencyContacts', 'Minimum Emergency Contacts', 'Minimum number of emergency contacts required', '2')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Child Enrollment', 'minAuthorizedPickups', 'Minimum Authorized Pickups', 'Minimum number of authorized pickup persons required', '1')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Child Enrollment', 'facilityName', 'Facility Name', 'Name of the childcare facility for PDF documents', '')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Child Enrollment', 'facilityAddress', 'Facility Address', 'Address of the childcare facility for PDF documents', '')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Child Enrollment', 'facilityPhone', 'Facility Phone', 'Phone number of the childcare facility', '')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Child Enrollment', 'facilityPermitNumber', 'Facility Permit Number', 'Quebec Ministry of Family permit number', '')
    ON DUPLICATE KEY UPDATE scope=scope;";

// Action Rows - define permissions for each page
$actionRows = [];

$actionRows[] = [
    'name'                      => 'Enrollment Forms',
    'precedence'                => 0,
    'category'                  => 'Enrollment',
    'description'               => 'View and manage child enrollment forms (Fiche d\'Inscription).',
    'URLList'                   => 'enrollment_list.php',
    'entryURL'                  => 'enrollment_list.php',
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
    'name'                      => 'View Enrollment Form',
    'precedence'                => 0,
    'category'                  => 'Enrollment',
    'description'               => 'View details of a child enrollment form.',
    'URLList'                   => 'enrollment_view.php',
    'entryURL'                  => 'enrollment_view.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'N',
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
    'name'                      => 'Add Enrollment Form',
    'precedence'                => 0,
    'category'                  => 'Enrollment',
    'description'               => 'Create a new child enrollment form.',
    'URLList'                   => 'enrollment_add.php,enrollment_addProcess.php',
    'entryURL'                  => 'enrollment_add.php',
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
    'name'                      => 'Edit Enrollment Form',
    'precedence'                => 0,
    'category'                  => 'Enrollment',
    'description'               => 'Edit an existing child enrollment form.',
    'URLList'                   => 'enrollment_edit.php,enrollment_editProcess.php',
    'entryURL'                  => 'enrollment_edit.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'N',
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
    'name'                      => 'Export Enrollment PDF',
    'precedence'                => 0,
    'category'                  => 'Enrollment',
    'description'               => 'Export enrollment form as PDF document.',
    'URLList'                   => 'enrollment_pdf.php',
    'entryURL'                  => 'enrollment_pdf.php',
    'entrySidebar'              => 'N',
    'menuShow'                  => 'N',
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
    'name'                      => 'Approve Enrollment Forms',
    'precedence'                => 0,
    'category'                  => 'Enrollment',
    'description'               => 'Approve or reject submitted enrollment forms.',
    'URLList'                   => 'enrollment_approve.php,enrollment_approveProcess.php',
    'entryURL'                  => 'enrollment_approve.php',
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

$actionRows[] = [
    'name'                      => 'Enrollment Settings',
    'precedence'                => 0,
    'category'                  => 'Enrollment',
    'description'               => 'Configure enrollment form settings.',
    'URLList'                   => 'enrollment_settings.php',
    'entryURL'                  => 'enrollment_settings.php',
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
