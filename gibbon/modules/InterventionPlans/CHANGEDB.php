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

// Intervention Plans Module - Database Change Log
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = array();
$count = 0;

// v1.0.00 - Initial release
++$count;
$sql[$count][0] = '1.0.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonInterventionPlan` (
    `gibbonInterventionPlanID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Child the plan is for',
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `aiServicePlanID` VARCHAR(36) NULL COMMENT 'UUID reference to ai-service intervention_plans table',
    `title` VARCHAR(200) NOT NULL,
    `status` ENUM('Draft','Active','Under Review','Completed','Archived') NOT NULL DEFAULT 'Draft',
    `version` INT UNSIGNED NOT NULL DEFAULT 1,
    `reviewSchedule` ENUM('Monthly','Quarterly','Biannually','Annually') NOT NULL DEFAULT 'Quarterly',
    `nextReviewDate` DATE NULL,
    `effectiveDate` DATE NULL,
    `endDate` DATE NULL,
    `parentSigned` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `parentSignatureDate` DATETIME NULL,
    `createdByID` INT UNSIGNED NOT NULL COMMENT 'Staff who created the plan',
    `lastModifiedByID` INT UNSIGNED NULL COMMENT 'Staff who last modified',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `gibbonSchoolYearID` (`gibbonSchoolYearID`),
    KEY `status` (`status`),
    KEY `nextReviewDate` (`nextReviewDate`),
    KEY `aiServicePlanID` (`aiServicePlanID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonInterventionStrength` (
    `gibbonInterventionStrengthID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonInterventionPlanID` INT UNSIGNED NOT NULL,
    `category` ENUM('Cognitive','Social','Physical','Emotional','Communication','Creative','Other') NOT NULL,
    `description` TEXT NOT NULL,
    `examples` TEXT NULL,
    `sortOrder` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonInterventionPlanID` (`gibbonInterventionPlanID`),
    CONSTRAINT `fk_strength_plan` FOREIGN KEY (`gibbonInterventionPlanID`) REFERENCES `gibbonInterventionPlan` (`gibbonInterventionPlanID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonInterventionNeed` (
    `gibbonInterventionNeedID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonInterventionPlanID` INT UNSIGNED NOT NULL,
    `category` ENUM('Communication','Behavior','Academic','Sensory','Motor','Social','Self-Care','Other') NOT NULL,
    `description` TEXT NOT NULL,
    `priority` ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
    `baseline` TEXT NULL COMMENT 'Baseline assessment of current ability',
    `sortOrder` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonInterventionPlanID` (`gibbonInterventionPlanID`),
    KEY `priority` (`priority`),
    CONSTRAINT `fk_need_plan` FOREIGN KEY (`gibbonInterventionPlanID`) REFERENCES `gibbonInterventionPlan` (`gibbonInterventionPlanID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonInterventionGoal` (
    `gibbonInterventionGoalID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonInterventionPlanID` INT UNSIGNED NOT NULL,
    `gibbonInterventionNeedID` INT UNSIGNED NULL COMMENT 'Optional link to need this goal addresses',
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NOT NULL COMMENT 'Specific - what the goal is',
    `measurementCriteria` TEXT NOT NULL COMMENT 'Measurable - how progress is measured',
    `measurementBaseline` VARCHAR(100) NULL,
    `measurementTarget` VARCHAR(100) NULL,
    `achievabilityNotes` TEXT NULL COMMENT 'Achievable - why goal is realistic',
    `relevanceNotes` TEXT NULL COMMENT 'Relevant - why goal matters',
    `targetDate` DATE NULL COMMENT 'Time-bound - deadline',
    `status` ENUM('Not Started','In Progress','Achieved','Modified','Discontinued') NOT NULL DEFAULT 'Not Started',
    `progressPercentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    `sortOrder` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonInterventionPlanID` (`gibbonInterventionPlanID`),
    KEY `status` (`status`),
    KEY `targetDate` (`targetDate`),
    CONSTRAINT `fk_goal_plan` FOREIGN KEY (`gibbonInterventionPlanID`) REFERENCES `gibbonInterventionPlan` (`gibbonInterventionPlanID`) ON DELETE CASCADE,
    CONSTRAINT `fk_goal_need` FOREIGN KEY (`gibbonInterventionNeedID`) REFERENCES `gibbonInterventionNeed` (`gibbonInterventionNeedID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonInterventionStrategy` (
    `gibbonInterventionStrategyID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonInterventionPlanID` INT UNSIGNED NOT NULL,
    `gibbonInterventionGoalID` INT UNSIGNED NULL COMMENT 'Optional link to goal this strategy supports',
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NOT NULL,
    `responsibleParty` ENUM('Educator','Parent','Therapist','Team','Other') NOT NULL DEFAULT 'Educator',
    `frequency` VARCHAR(100) NULL,
    `materialsNeeded` TEXT NULL,
    `accommodations` TEXT NULL,
    `sortOrder` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonInterventionPlanID` (`gibbonInterventionPlanID`),
    KEY `responsibleParty` (`responsibleParty`),
    CONSTRAINT `fk_strategy_plan` FOREIGN KEY (`gibbonInterventionPlanID`) REFERENCES `gibbonInterventionPlan` (`gibbonInterventionPlanID`) ON DELETE CASCADE,
    CONSTRAINT `fk_strategy_goal` FOREIGN KEY (`gibbonInterventionGoalID`) REFERENCES `gibbonInterventionGoal` (`gibbonInterventionGoalID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonInterventionMonitoring` (
    `gibbonInterventionMonitoringID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonInterventionPlanID` INT UNSIGNED NOT NULL,
    `gibbonInterventionGoalID` INT UNSIGNED NULL COMMENT 'Optional link to goal being monitored',
    `method` ENUM('Observation','Assessment','Data Collection','Checklist','Portfolio','Other') NOT NULL,
    `description` TEXT NOT NULL,
    `frequency` ENUM('Daily','Weekly','Biweekly','Monthly','Quarterly') NOT NULL DEFAULT 'Weekly',
    `responsibleParty` ENUM('Educator','Parent','Therapist','Team','Other') NOT NULL DEFAULT 'Educator',
    `dataCollectionTools` TEXT NULL,
    `successIndicators` TEXT NULL,
    `sortOrder` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonInterventionPlanID` (`gibbonInterventionPlanID`),
    KEY `method` (`method`),
    CONSTRAINT `fk_monitoring_plan` FOREIGN KEY (`gibbonInterventionPlanID`) REFERENCES `gibbonInterventionPlan` (`gibbonInterventionPlanID`) ON DELETE CASCADE,
    CONSTRAINT `fk_monitoring_goal` FOREIGN KEY (`gibbonInterventionGoalID`) REFERENCES `gibbonInterventionGoal` (`gibbonInterventionGoalID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonInterventionParentInvolvement` (
    `gibbonInterventionParentInvolvementID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonInterventionPlanID` INT UNSIGNED NOT NULL,
    `activityType` ENUM('Home Activity','Communication','Training','Meeting','Resources','Other') NOT NULL,
    `title` VARCHAR(200) NOT NULL,
    `description` TEXT NOT NULL,
    `frequency` VARCHAR(50) NULL,
    `resourcesProvided` TEXT NULL,
    `communicationMethod` VARCHAR(100) NULL,
    `sortOrder` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonInterventionPlanID` (`gibbonInterventionPlanID`),
    KEY `activityType` (`activityType`),
    CONSTRAINT `fk_parent_involvement_plan` FOREIGN KEY (`gibbonInterventionPlanID`) REFERENCES `gibbonInterventionPlan` (`gibbonInterventionPlanID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonInterventionConsultation` (
    `gibbonInterventionConsultationID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonInterventionPlanID` INT UNSIGNED NOT NULL,
    `specialistType` ENUM('Speech Therapist','Occupational Therapist','Psychologist','Pediatrician','Behavioral Specialist','Special Education Consultant','Other') NOT NULL,
    `specialistName` VARCHAR(200) NULL,
    `organization` VARCHAR(200) NULL,
    `purpose` TEXT NOT NULL,
    `recommendations` TEXT NULL,
    `consultationDate` DATE NULL,
    `nextConsultationDate` DATE NULL,
    `notes` TEXT NULL,
    `sortOrder` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonInterventionPlanID` (`gibbonInterventionPlanID`),
    KEY `specialistType` (`specialistType`),
    KEY `consultationDate` (`consultationDate`),
    CONSTRAINT `fk_consultation_plan` FOREIGN KEY (`gibbonInterventionPlanID`) REFERENCES `gibbonInterventionPlan` (`gibbonInterventionPlanID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonInterventionProgress` (
    `gibbonInterventionProgressID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonInterventionPlanID` INT UNSIGNED NOT NULL,
    `gibbonInterventionGoalID` INT UNSIGNED NULL COMMENT 'Optional link to specific goal',
    `recordedByID` INT UNSIGNED NOT NULL COMMENT 'Staff who recorded progress',
    `recordDate` DATE NOT NULL,
    `progressNotes` TEXT NOT NULL,
    `progressLevel` ENUM('No Progress','Minimal','Moderate','Significant','Achieved') NOT NULL DEFAULT 'Minimal',
    `measurementValue` VARCHAR(100) NULL,
    `barriers` TEXT NULL,
    `nextSteps` TEXT NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonInterventionPlanID` (`gibbonInterventionPlanID`),
    KEY `recordDate` (`recordDate`),
    KEY `progressLevel` (`progressLevel`),
    CONSTRAINT `fk_progress_plan` FOREIGN KEY (`gibbonInterventionPlanID`) REFERENCES `gibbonInterventionPlan` (`gibbonInterventionPlanID`) ON DELETE CASCADE,
    CONSTRAINT `fk_progress_goal` FOREIGN KEY (`gibbonInterventionGoalID`) REFERENCES `gibbonInterventionGoal` (`gibbonInterventionGoalID`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonInterventionVersion` (
    `gibbonInterventionVersionID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonInterventionPlanID` INT UNSIGNED NOT NULL,
    `versionNumber` INT UNSIGNED NOT NULL,
    `createdByID` INT UNSIGNED NOT NULL,
    `changeSummary` TEXT NULL,
    `snapshotData` LONGTEXT NULL COMMENT 'JSON snapshot of full plan at this version',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonInterventionPlanID` (`gibbonInterventionPlanID`),
    KEY `versionNumber` (`versionNumber`),
    UNIQUE KEY `plan_version` (`gibbonInterventionPlanID`, `versionNumber`),
    CONSTRAINT `fk_version_plan` FOREIGN KEY (`gibbonInterventionPlanID`) REFERENCES `gibbonInterventionPlan` (`gibbonInterventionPlanID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Intervention Plans', 'defaultReviewSchedule', 'Default Review Schedule', 'Default schedule for plan reviews', 'Quarterly') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Intervention Plans', 'requireParentSignature', 'Require Parent Signature', 'Require parent signature before plan becomes active', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Intervention Plans', 'reviewReminderDays', 'Review Reminder Days', 'Days before review date to send reminder notifications', '14') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Intervention Plans', 'aiServiceIntegration', 'AI Service Integration', 'Enable integration with AI service for plan analysis', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Intervention Plans', 'aiServiceBaseURL', 'AI Service Base URL', 'Base URL for the AI service API', 'http://localhost:8000/api/v1') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Intervention Plans', 'autoSyncWithAIService', 'Auto Sync with AI Service', 'Automatically sync plans with AI service on save', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
";

// v1.0.01 - Bug fixes and minor improvements
++$count;
$sql[$count][0] = '1.0.01';
$sql[$count][1] = "";

// v1.1.00 - Enhanced SMART goal tracking and AI integration
++$count;
$sql[$count][0] = '1.1.00';
$sql[$count][1] = "
ALTER TABLE `gibbonInterventionPlan` ADD COLUMN `childName` VARCHAR(200) NULL COMMENT 'Cached child name for quick display' AFTER `aiServicePlanID`;end
ALTER TABLE `gibbonInterventionPlan` ADD COLUMN `dateOfBirth` DATE NULL AFTER `childName`;end
ALTER TABLE `gibbonInterventionPlan` ADD COLUMN `diagnosis` TEXT NULL COMMENT 'JSON array of diagnoses' AFTER `dateOfBirth`;end
ALTER TABLE `gibbonInterventionPlan` ADD COLUMN `medicalHistory` TEXT NULL AFTER `diagnosis`;end
ALTER TABLE `gibbonInterventionPlan` ADD COLUMN `educationalHistory` TEXT NULL AFTER `medicalHistory`;end
ALTER TABLE `gibbonInterventionPlan` ADD COLUMN `familyContext` TEXT NULL AFTER `educationalHistory`;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Intervention Plans', 'enableSMARTGoalValidation', 'Enable SMART Goal Validation', 'Validate that goals meet SMART criteria before saving', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Intervention Plans', 'enableProgressNotifications', 'Enable Progress Notifications', 'Send notifications when progress is recorded', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
";

// v1.2.00 - Parent portal integration and signature enhancements
++$count;
$sql[$count][0] = '1.2.00';
$sql[$count][1] = "
ALTER TABLE `gibbonInterventionPlan` ADD COLUMN `parentSignatureData` LONGTEXT NULL COMMENT 'Base64 encoded signature image' AFTER `parentSignatureDate`;end
ALTER TABLE `gibbonInterventionPlan` ADD COLUMN `parentAcknowledgedTerms` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `parentSignatureData`;end
ALTER TABLE `gibbonInterventionPlan` ADD COLUMN `parentPortalVisible` ENUM('Y','N') NOT NULL DEFAULT 'Y' COMMENT 'Whether plan is visible to parents in portal' AFTER `parentAcknowledgedTerms`;end
CREATE TABLE IF NOT EXISTS `gibbonInterventionPlanNotification` (
    `gibbonInterventionPlanNotificationID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonInterventionPlanID` INT UNSIGNED NOT NULL,
    `recipientPersonID` INT UNSIGNED NOT NULL COMMENT 'Parent/guardian receiving notification',
    `type` ENUM('Review Reminder','Progress Update','Signature Request','Plan Updated','Goal Achieved') NOT NULL,
    `message` TEXT NOT NULL,
    `status` ENUM('Pending','Sent','Failed','Read') NOT NULL DEFAULT 'Pending',
    `sentAt` DATETIME NULL,
    `readAt` DATETIME NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonInterventionPlanID` (`gibbonInterventionPlanID`),
    KEY `recipientPersonID` (`recipientPersonID`),
    KEY `status` (`status`),
    CONSTRAINT `fk_notification_plan` FOREIGN KEY (`gibbonInterventionPlanID`) REFERENCES `gibbonInterventionPlan` (`gibbonInterventionPlanID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Intervention Plans', 'notifyParentOnReviewDue', 'Notify Parent on Review Due', 'Send notification to parents when plan review is due', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Intervention Plans', 'notifyParentOnProgress', 'Notify Parent on Progress', 'Send notification to parents when progress is recorded', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
";

// v1.3.00 - Audit logging and compliance features
++$count;
$sql[$count][0] = '1.3.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonInterventionAuditLog` (
    `gibbonInterventionAuditLogID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonInterventionPlanID` INT UNSIGNED NOT NULL,
    `tableName` VARCHAR(50) NOT NULL,
    `recordID` INT UNSIGNED NOT NULL,
    `action` ENUM('INSERT','UPDATE','DELETE') NOT NULL,
    `fieldName` VARCHAR(50) NULL,
    `oldValue` TEXT NULL,
    `newValue` TEXT NULL,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'User who made change',
    `ipAddress` VARCHAR(45) NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonInterventionPlanID` (`gibbonInterventionPlanID`),
    KEY `tableName` (`tableName`),
    KEY `recordID` (`recordID`),
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `timestampCreated` (`timestampCreated`),
    CONSTRAINT `fk_audit_plan` FOREIGN KEY (`gibbonInterventionPlanID`) REFERENCES `gibbonInterventionPlan` (`gibbonInterventionPlanID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Intervention Plans', 'auditLoggingEnabled', 'Audit Logging Enabled', 'Enable detailed audit logging for compliance', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Intervention Plans', 'dataRetentionYears', 'Data Retention Years', 'Number of years to retain intervention plan data', '7') ON DUPLICATE KEY UPDATE scope=scope;end
";
