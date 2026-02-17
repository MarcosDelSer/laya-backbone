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
$name        = 'Staff Management';
$description = 'Comprehensive HR module with employee files, work scheduling, time tracking, certification management, and Quebec staff-to-child ratio compliance monitoring.';
$entryURL    = 'staffManagement.php';
$type        = 'Additional';
$category    = 'HR';
$version     = '1.0.00';
$author      = 'LAYA';
$url         = 'https://laya.ca';

// Module tables & gibbonSetting entries
$moduleTables = [];

$moduleTables[] = "CREATE TABLE `gibbonStaffProfile` (
    `gibbonStaffProfileID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Link to gibbonPerson',
    `employeeNumber` VARCHAR(50) NULL COMMENT 'Internal employee ID',
    `sin` VARCHAR(20) NULL COMMENT 'Social Insurance Number (encrypted)',
    `address` TEXT NULL COMMENT 'Full home address',
    `city` VARCHAR(100) NULL,
    `province` VARCHAR(50) NULL,
    `postalCode` VARCHAR(20) NULL,
    `position` VARCHAR(100) NOT NULL COMMENT 'Job title/position',
    `department` VARCHAR(100) NULL,
    `employmentType` ENUM('Full-Time','Part-Time','Casual','Contract','Substitute') NOT NULL DEFAULT 'Full-Time',
    `hireDate` DATE NULL COMMENT 'Date of hire',
    `terminationDate` DATE NULL COMMENT 'Date of termination if applicable',
    `probationEndDate` DATE NULL COMMENT 'End of probation period',
    `status` ENUM('Active','Inactive','On Leave','Terminated') NOT NULL DEFAULT 'Active',
    `qualificationLevel` ENUM('Unqualified','Level 1','Level 2','Level 3','Director') NULL COMMENT 'Quebec childcare qualification level',
    `insuranceProvider` VARCHAR(100) NULL COMMENT 'Health insurance provider',
    `insurancePolicyNumber` VARCHAR(100) NULL COMMENT 'Health insurance policy number',
    `groupInsuranceEnrolled` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `bankInstitution` VARCHAR(10) NULL COMMENT 'Bank institution number for payroll',
    `bankTransit` VARCHAR(10) NULL COMMENT 'Bank transit number for payroll',
    `bankAccount` VARCHAR(20) NULL COMMENT 'Bank account number for payroll',
    `notes` TEXT NULL COMMENT 'Internal HR notes',
    `createdByID` INT UNSIGNED NOT NULL COMMENT 'Staff who created record',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `status` (`status`),
    KEY `position` (`position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$moduleTables[] = "CREATE TABLE `gibbonStaffCertification` (
    `gibbonStaffCertificationID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Link to gibbonPerson',
    `certificationType` ENUM('Criminal Background Check','Child Abuse Registry','First Aid','CPR','Early Childhood Education','Other') NOT NULL,
    `certificationName` VARCHAR(200) NOT NULL COMMENT 'Full name of certification',
    `issuingOrganization` VARCHAR(200) NULL COMMENT 'Organization that issued certification',
    `certificateNumber` VARCHAR(100) NULL COMMENT 'Certificate/license number',
    `issueDate` DATE NULL COMMENT 'Date certification was issued',
    `expiryDate` DATE NULL COMMENT 'Date certification expires',
    `isRequired` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Is this a mandatory certification?',
    `status` ENUM('Valid','Expired','Pending','Revoked') NOT NULL DEFAULT 'Valid',
    `documentPath` VARCHAR(500) NULL COMMENT 'Path to uploaded certificate document',
    `reminderSent` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Has expiry reminder been sent?',
    `reminderSentDate` DATE NULL COMMENT 'Date reminder was sent',
    `notes` TEXT NULL,
    `recordedByID` INT UNSIGNED NOT NULL COMMENT 'Staff who recorded',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `certificationType` (`certificationType`),
    KEY `expiryDate` (`expiryDate`),
    KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$moduleTables[] = "CREATE TABLE `gibbonStaffEmergencyContact` (
    `gibbonStaffEmergencyContactID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Link to gibbonPerson (staff member)',
    `contactName` VARCHAR(200) NOT NULL COMMENT 'Full name of emergency contact',
    `relationship` VARCHAR(100) NOT NULL COMMENT 'Relationship to staff member',
    `phone1` VARCHAR(30) NOT NULL COMMENT 'Primary phone number',
    `phone2` VARCHAR(30) NULL COMMENT 'Secondary phone number',
    `email` VARCHAR(255) NULL COMMENT 'Email address',
    `address` TEXT NULL COMMENT 'Full address of contact',
    `priority` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Contact priority order',
    `notes` TEXT NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `priority` (`priority`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// gibbonSetting entries
$gibbonSetting = [];

$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Staff Management', 'certificationExpiryWarningDays', 'Certification Expiry Warning Days', 'Number of days before certification expiry to send warning notifications', '30');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Staff Management', 'requiredCertifications', 'Required Certifications', 'Comma-separated list of required certification types for all staff', 'Criminal Background Check,Child Abuse Registry,First Aid,CPR');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Staff Management', 'ratioInfant', 'Staff-to-Child Ratio (0-18 months)', 'Quebec required staff-to-child ratio for infants', '5');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Staff Management', 'ratioToddler', 'Staff-to-Child Ratio (18-36 months)', 'Quebec required staff-to-child ratio for toddlers', '8');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Staff Management', 'ratioPreschool', 'Staff-to-Child Ratio (36-60 months)', 'Quebec required staff-to-child ratio for preschoolers', '10');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Staff Management', 'ratioSchoolAge', 'Staff-to-Child Ratio (60+ months)', 'Quebec required staff-to-child ratio for school-age children', '20');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Staff Management', 'enableAuditLog', 'Enable Audit Log', 'Track all modifications to staff records', 'Y');";

// Action rows for gibbonAction
$actionRows = [];

$actionRows[] = [
    'name'                      => 'Staff Management Dashboard',
    'precedence'                => 0,
    'category'                  => 'Staff',
    'description'               => 'View staff management dashboard with overview and quick actions.',
    'URLList'                   => 'staffManagement.php',
    'entryURL'                  => 'staffManagement.php',
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
    'name'                      => 'Staff Profiles',
    'precedence'                => 0,
    'category'                  => 'Staff',
    'description'               => 'View and manage staff employee files and profiles.',
    'URLList'                   => 'staffManagement_profile.php,staffManagement_addEdit.php',
    'entryURL'                  => 'staffManagement_profile.php',
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
    'name'                      => 'Staff Scheduling',
    'precedence'                => 0,
    'category'                  => 'Scheduling',
    'description'               => 'Manage staff work schedules and shift templates.',
    'URLList'                   => 'staffManagement_schedule.php,staffManagement_shiftTemplates.php,staffManagement_availability.php',
    'entryURL'                  => 'staffManagement_schedule.php',
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
    'name'                      => 'Time Tracking',
    'precedence'                => 0,
    'category'                  => 'Time',
    'description'               => 'Staff clock-in/out and hours tracking.',
    'URLList'                   => 'staffManagement_timeTracking.php,staffManagement_hoursReport.php',
    'entryURL'                  => 'staffManagement_timeTracking.php',
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
    'name'                      => 'Certification Management',
    'precedence'                => 0,
    'category'                  => 'Compliance',
    'description'               => 'Track staff certifications and expiration dates.',
    'URLList'                   => 'staffManagement_certifications.php,staffManagement_renewals.php',
    'entryURL'                  => 'staffManagement_certifications.php',
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
    'name'                      => 'Ratio Compliance Monitor',
    'precedence'                => 0,
    'category'                  => 'Compliance',
    'description'               => 'Real-time Quebec staff-to-child ratio monitoring.',
    'URLList'                   => 'staffManagement_ratioMonitor.php,staffManagement_ratioHistory.php',
    'entryURL'                  => 'staffManagement_ratioMonitor.php',
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
    'name'                      => 'Disciplinary Records',
    'precedence'                => 0,
    'category'                  => 'Director Only',
    'description'               => 'Manage confidential disciplinary records (director access only).',
    'URLList'                   => 'staffManagement_disciplinary.php',
    'entryURL'                  => 'staffManagement_disciplinary.php',
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
    'name'                      => 'Audit Log',
    'precedence'                => 0,
    'category'                  => 'Director Only',
    'description'               => 'View audit trail of all staff record modifications.',
    'URLList'                   => 'staffManagement_auditLog.php',
    'entryURL'                  => 'staffManagement_auditLog.php',
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
    'name'                      => 'Staff Management Settings',
    'precedence'                => 0,
    'category'                  => 'Settings',
    'description'               => 'Configure Staff Management module settings.',
    'URLList'                   => 'staffManagement_settings.php',
    'entryURL'                  => 'staffManagement_settings.php',
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
