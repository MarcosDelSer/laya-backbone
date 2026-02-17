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

// Staff Management Module - Database Change Log
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = array();
$count = 0;

// v1.0.00 - Initial release
++$count;
$sql[$count][0] = '1.0.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonStaffProfile` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonStaffCertification` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonStaffEmergencyContact` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'certificationExpiryWarningDays', 'Certification Expiry Warning Days', 'Number of days before certification expiry to send warning notifications', '30') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'requiredCertifications', 'Required Certifications', 'Comma-separated list of required certification types for all staff', 'Criminal Background Check,Child Abuse Registry,First Aid,CPR') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'ratioInfant', 'Staff-to-Child Ratio (0-18 months)', 'Quebec required staff-to-child ratio for infants (1:X)', '5') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'ratioToddler', 'Staff-to-Child Ratio (18-36 months)', 'Quebec required staff-to-child ratio for toddlers (1:X)', '8') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'ratioPreschool', 'Staff-to-Child Ratio (36-60 months)', 'Quebec required staff-to-child ratio for preschoolers (1:X)', '10') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'ratioSchoolAge', 'Staff-to-Child Ratio (60+ months)', 'Quebec required staff-to-child ratio for school-age children (1:X)', '20') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'enableAuditLog', 'Enable Audit Log', 'Track all modifications to staff records', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
";

// v1.0.01 - Bug fixes and minor improvements
++$count;
$sql[$count][0] = '1.0.01';
$sql[$count][1] = "";

// v1.1.00 - Work scheduling and shift templates
++$count;
$sql[$count][0] = '1.1.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonStaffShiftTemplate` (
    `gibbonStaffShiftTemplateID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL COMMENT 'Template name (e.g., Morning Shift, Full Day)',
    `description` TEXT NULL,
    `startTime` TIME NOT NULL COMMENT 'Default start time',
    `endTime` TIME NOT NULL COMMENT 'Default end time',
    `breakDuration` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Break duration in minutes',
    `color` VARCHAR(7) NULL COMMENT 'Hex color for calendar display',
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `createdByID` INT UNSIGNED NOT NULL COMMENT 'Staff who created template',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonStaffSchedule` (
    `gibbonStaffScheduleID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Link to gibbonPerson (staff)',
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `gibbonStaffShiftTemplateID` INT UNSIGNED NULL COMMENT 'Optional link to shift template',
    `date` DATE NOT NULL COMMENT 'Scheduled work date',
    `startTime` TIME NOT NULL COMMENT 'Scheduled start time',
    `endTime` TIME NOT NULL COMMENT 'Scheduled end time',
    `breakDuration` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Break duration in minutes',
    `roomAssignment` VARCHAR(100) NULL COMMENT 'Assigned room/classroom',
    `ageGroup` ENUM('Infant','Toddler','Preschool','School Age','Mixed') NULL COMMENT 'Age group assigned to',
    `status` ENUM('Scheduled','Confirmed','Completed','Cancelled','No Show') NOT NULL DEFAULT 'Scheduled',
    `notes` TEXT NULL,
    `createdByID` INT UNSIGNED NOT NULL COMMENT 'Staff who created schedule',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `date` (`date`),
    KEY `gibbonSchoolYearID` (`gibbonSchoolYearID`),
    KEY `status` (`status`),
    KEY `ageGroup` (`ageGroup`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'defaultBreakDuration', 'Default Break Duration', 'Default break duration in minutes for shifts over 5 hours', '30') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'scheduleViewWeeks', 'Schedule View Weeks', 'Number of weeks to display in schedule view', '4') ON DUPLICATE KEY UPDATE scope=scope;end
";

// v1.2.00 - Time tracking and clock-in/out
++$count;
$sql[$count][0] = '1.2.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonStaffTimeEntry` (
    `gibbonStaffTimeEntryID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Link to gibbonPerson (staff)',
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `gibbonStaffScheduleID` INT UNSIGNED NULL COMMENT 'Optional link to scheduled shift',
    `date` DATE NOT NULL COMMENT 'Work date',
    `clockInTime` DATETIME NULL COMMENT 'Actual clock-in timestamp',
    `clockOutTime` DATETIME NULL COMMENT 'Actual clock-out timestamp',
    `breakStart` DATETIME NULL COMMENT 'Break start time',
    `breakEnd` DATETIME NULL COMMENT 'Break end time',
    `totalBreakMinutes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total break time in minutes',
    `totalWorkedMinutes` INT UNSIGNED NULL COMMENT 'Total worked time in minutes',
    `overtime` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Is this overtime?',
    `overtimeMinutes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Overtime minutes',
    `overtimeApproved` ENUM('Y','N','Pending') NOT NULL DEFAULT 'Pending' COMMENT 'Overtime approval status',
    `overtimeApprovedByID` INT UNSIGNED NULL COMMENT 'Staff who approved overtime',
    `clockInMethod` ENUM('Manual','PIN','Biometric','GPS','QR') NOT NULL DEFAULT 'Manual',
    `clockOutMethod` ENUM('Manual','PIN','Biometric','GPS','QR') NULL,
    `clockInLocation` VARCHAR(255) NULL COMMENT 'GPS coordinates or location name',
    `clockOutLocation` VARCHAR(255) NULL COMMENT 'GPS coordinates or location name',
    `status` ENUM('Active','Completed','Adjusted','Cancelled') NOT NULL DEFAULT 'Active',
    `adjustmentReason` TEXT NULL COMMENT 'Reason for manual adjustment',
    `adjustedByID` INT UNSIGNED NULL COMMENT 'Staff who adjusted entry',
    `notes` TEXT NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `date` (`date`),
    KEY `gibbonSchoolYearID` (`gibbonSchoolYearID`),
    KEY `status` (`status`),
    UNIQUE KEY `personDate` (`gibbonPersonID`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'overtimeThreshold', 'Overtime Threshold', 'Minutes per day before overtime kicks in', '480') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'lateThreshold', 'Late Threshold', 'Minutes after scheduled start to be considered late', '5') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'requireClockLocation', 'Require Clock Location', 'Require GPS location for clock-in/out', 'N') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'clockInMethods', 'Allowed Clock-In Methods', 'Comma-separated list of allowed clock-in methods', 'Manual,PIN,QR') ON DUPLICATE KEY UPDATE scope=scope;end
";

// v1.3.00 - Ratio compliance monitoring
++$count;
$sql[$count][0] = '1.3.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonStaffRatioSnapshot` (
    `gibbonStaffRatioSnapshotID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `snapshotDate` DATE NOT NULL COMMENT 'Date of snapshot',
    `snapshotTime` TIME NOT NULL COMMENT 'Time of snapshot',
    `ageGroup` ENUM('Infant','Toddler','Preschool','School Age') NOT NULL,
    `roomName` VARCHAR(100) NULL COMMENT 'Room/classroom name',
    `staffCount` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of staff present',
    `childCount` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of children present',
    `requiredRatio` INT UNSIGNED NOT NULL COMMENT 'Required children per staff',
    `actualRatio` DECIMAL(5,2) NOT NULL COMMENT 'Actual children per staff',
    `isCompliant` ENUM('Y','N') NOT NULL DEFAULT 'Y' COMMENT 'Is ratio compliant?',
    `compliancePercent` DECIMAL(5,2) NULL COMMENT 'Percentage of compliance (100% = exactly meeting ratio)',
    `alertSent` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Was alert sent for non-compliance?',
    `alertSentTime` DATETIME NULL COMMENT 'When alert was sent',
    `notes` TEXT NULL,
    `recordedByID` INT UNSIGNED NULL COMMENT 'Staff who recorded (if manual)',
    `isAutomatic` ENUM('Y','N') NOT NULL DEFAULT 'Y' COMMENT 'Was this auto-captured?',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `snapshotDate` (`snapshotDate`),
    KEY `ageGroup` (`ageGroup`),
    KEY `isCompliant` (`isCompliant`),
    KEY `gibbonSchoolYearID` (`gibbonSchoolYearID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'ratioSnapshotInterval', 'Ratio Snapshot Interval', 'Minutes between automatic ratio snapshots', '30') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'ratioAlertEnabled', 'Ratio Alert Enabled', 'Send alerts when ratio is non-compliant', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'ratioAlertRecipients', 'Ratio Alert Recipients', 'Comma-separated list of roles to receive ratio alerts', 'Administrator,Director') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'ratioWarningThreshold', 'Ratio Warning Threshold', 'Percentage of ratio to trigger warning (e.g., 90 means warn at 90% capacity)', '90') ON DUPLICATE KEY UPDATE scope=scope;end
";

// v1.4.00 - Disciplinary records and audit logging
++$count;
$sql[$count][0] = '1.4.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonStaffDisciplinary` (
    `gibbonStaffDisciplinaryID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Link to gibbonPerson (staff)',
    `incidentDate` DATE NOT NULL COMMENT 'Date of incident',
    `incidentTime` TIME NULL COMMENT 'Time of incident',
    `type` ENUM('Verbal Warning','Written Warning','Suspension','Probation','Performance Improvement Plan','Termination','Other') NOT NULL,
    `severity` ENUM('Minor','Moderate','Serious','Critical') NOT NULL DEFAULT 'Minor',
    `category` ENUM('Attendance','Performance','Conduct','Policy Violation','Safety','Other') NOT NULL,
    `description` TEXT NOT NULL COMMENT 'Detailed description of incident',
    `actionTaken` TEXT NULL COMMENT 'Actions taken in response',
    `employeeResponse` TEXT NULL COMMENT 'Employee response or comments',
    `followUpRequired` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `followUpDate` DATE NULL COMMENT 'Date for follow-up review',
    `followUpCompleted` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `followUpNotes` TEXT NULL COMMENT 'Notes from follow-up',
    `witnessNames` TEXT NULL COMMENT 'Names of witnesses',
    `documentPath` VARCHAR(500) NULL COMMENT 'Path to related documents',
    `confidential` ENUM('Y','N') NOT NULL DEFAULT 'Y' COMMENT 'Is this record confidential?',
    `status` ENUM('Open','Under Review','Resolved','Appealed','Archived') NOT NULL DEFAULT 'Open',
    `resolutionDate` DATE NULL COMMENT 'Date issue was resolved',
    `resolutionNotes` TEXT NULL COMMENT 'Resolution details',
    `recordedByID` INT UNSIGNED NOT NULL COMMENT 'Director/HR who recorded',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `incidentDate` (`incidentDate`),
    KEY `type` (`type`),
    KEY `status` (`status`),
    KEY `severity` (`severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonStaffAuditLog` (
    `gibbonStaffAuditLogID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tableName` VARCHAR(100) NOT NULL COMMENT 'Name of table modified',
    `recordID` INT UNSIGNED NOT NULL COMMENT 'ID of record modified',
    `action` ENUM('INSERT','UPDATE','DELETE') NOT NULL,
    `fieldName` VARCHAR(100) NULL COMMENT 'Name of field changed',
    `oldValue` TEXT NULL COMMENT 'Previous value',
    `newValue` TEXT NULL COMMENT 'New value',
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'User who made change',
    `ipAddress` VARCHAR(45) NULL COMMENT 'IP address of user',
    `userAgent` VARCHAR(500) NULL COMMENT 'Browser user agent',
    `sessionID` VARCHAR(100) NULL COMMENT 'Session identifier',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `tableName` (`tableName`),
    KEY `recordID` (`recordID`),
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `action` (`action`),
    KEY `timestampCreated` (`timestampCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'disciplinaryRetentionYears', 'Disciplinary Record Retention', 'Number of years to retain disciplinary records', '7') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'auditLogRetentionDays', 'Audit Log Retention Days', 'Number of days to retain audit log entries', '365') ON DUPLICATE KEY UPDATE scope=scope;end
";

// v1.5.00 - Staff availability and leave tracking
++$count;
$sql[$count][0] = '1.5.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonStaffAvailability` (
    `gibbonStaffAvailabilityID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Link to gibbonPerson (staff)',
    `dayOfWeek` ENUM('Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday') NOT NULL,
    `availableFrom` TIME NULL COMMENT 'Available from time',
    `availableTo` TIME NULL COMMENT 'Available until time',
    `isAvailable` ENUM('Y','N') NOT NULL DEFAULT 'Y' COMMENT 'Is available on this day?',
    `preferredHours` INT UNSIGNED NULL COMMENT 'Preferred hours per week',
    `maxHours` INT UNSIGNED NULL COMMENT 'Maximum hours per week',
    `notes` TEXT NULL,
    `effectiveFrom` DATE NULL COMMENT 'When this availability starts',
    `effectiveTo` DATE NULL COMMENT 'When this availability ends',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `dayOfWeek` (`dayOfWeek`),
    UNIQUE KEY `personDay` (`gibbonPersonID`, `dayOfWeek`, `effectiveFrom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonStaffLeave` (
    `gibbonStaffLeaveID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Link to gibbonPerson (staff)',
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `leaveType` ENUM('Vacation','Sick','Personal','Bereavement','Maternity','Paternity','Unpaid','Other') NOT NULL,
    `startDate` DATE NOT NULL,
    `endDate` DATE NOT NULL,
    `startTime` TIME NULL COMMENT 'Partial day - start time',
    `endTime` TIME NULL COMMENT 'Partial day - end time',
    `isPartialDay` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `totalDays` DECIMAL(4,2) NOT NULL DEFAULT 1.00 COMMENT 'Total days taken',
    `reason` TEXT NULL COMMENT 'Reason for leave',
    `status` ENUM('Pending','Approved','Denied','Cancelled') NOT NULL DEFAULT 'Pending',
    `approvedByID` INT UNSIGNED NULL COMMENT 'Staff who approved',
    `approvedDate` DATETIME NULL,
    `denialReason` TEXT NULL COMMENT 'Reason for denial',
    `documentPath` VARCHAR(500) NULL COMMENT 'Path to supporting documents',
    `requestedByID` INT UNSIGNED NOT NULL COMMENT 'Staff who requested',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `startDate` (`startDate`),
    KEY `leaveType` (`leaveType`),
    KEY `status` (`status`),
    KEY `gibbonSchoolYearID` (`gibbonSchoolYearID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

ALTER TABLE `gibbonStaffProfile` ADD COLUMN `vacationDaysEntitled` DECIMAL(5,2) NOT NULL DEFAULT 10.00 COMMENT 'Annual vacation days entitled' AFTER `groupInsuranceEnrolled`;end
ALTER TABLE `gibbonStaffProfile` ADD COLUMN `vacationDaysUsed` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Vacation days used this year' AFTER `vacationDaysEntitled`;end
ALTER TABLE `gibbonStaffProfile` ADD COLUMN `sickDaysEntitled` DECIMAL(5,2) NOT NULL DEFAULT 6.00 COMMENT 'Annual sick days entitled' AFTER `vacationDaysUsed`;end
ALTER TABLE `gibbonStaffProfile` ADD COLUMN `sickDaysUsed` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Sick days used this year' AFTER `sickDaysEntitled`;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'defaultVacationDays', 'Default Vacation Days', 'Default annual vacation days for new staff', '10') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'defaultSickDays', 'Default Sick Days', 'Default annual sick days for new staff', '6') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'leaveApprovalRequired', 'Leave Approval Required', 'Require director approval for leave requests', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
";

// v1.6.00 - Room assignments and staff grouping
++$count;
$sql[$count][0] = '1.6.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonStaffRoomAssignment` (
    `gibbonStaffRoomAssignmentID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Link to gibbonPerson (staff)',
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `roomName` VARCHAR(100) NOT NULL COMMENT 'Room/classroom name',
    `ageGroup` ENUM('Infant','Toddler','Preschool','School Age','Mixed') NOT NULL,
    `assignmentType` ENUM('Primary','Secondary','Float','Substitute') NOT NULL DEFAULT 'Primary',
    `effectiveDate` DATE NOT NULL COMMENT 'When assignment starts',
    `endDate` DATE NULL COMMENT 'When assignment ends',
    `isActive` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `notes` TEXT NULL,
    `assignedByID` INT UNSIGNED NOT NULL COMMENT 'Staff who made assignment',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `roomName` (`roomName`),
    KEY `ageGroup` (`ageGroup`),
    KEY `isActive` (`isActive`),
    KEY `gibbonSchoolYearID` (`gibbonSchoolYearID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

ALTER TABLE `gibbonStaffSchedule` ADD COLUMN `gibbonStaffRoomAssignmentID` INT UNSIGNED NULL COMMENT 'Link to room assignment' AFTER `roomAssignment`;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Staff Management', 'rooms', 'Room Names', 'Comma-separated list of room names', 'Infant Room A,Infant Room B,Toddler Room A,Toddler Room B,Preschool Room,School Age Room') ON DUPLICATE KEY UPDATE scope=scope;end
";
