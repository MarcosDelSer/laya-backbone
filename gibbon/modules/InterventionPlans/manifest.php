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
$name        = 'Intervention Plans';
$description = 'Comprehensive intervention planning system with 8-part structure, SMART goals, versioning, progress tracking, and automated review reminders for special needs support.';
$entryURL    = 'interventionPlans.php';
$type        = 'Additional';
$category    = 'Special Education';
$version     = '1.0.00';
$author      = 'LAYA';
$url         = 'https://laya.ca';

// Module tables & gibbonSetting entries
$moduleTables = [];

// Main intervention plan table - links to ai-service for full plan data
$moduleTables[] = "CREATE TABLE `gibbonInterventionPlan` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Part 2 - Strengths
$moduleTables[] = "CREATE TABLE `gibbonInterventionStrength` (
    `gibbonInterventionStrengthID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonInterventionPlanID` INT UNSIGNED NOT NULL,
    `category` ENUM('Cognitive','Social','Physical','Emotional','Communication','Creative','Other') NOT NULL,
    `description` TEXT NOT NULL,
    `examples` TEXT NULL,
    `sortOrder` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonInterventionPlanID` (`gibbonInterventionPlanID`),
    CONSTRAINT `fk_strength_plan` FOREIGN KEY (`gibbonInterventionPlanID`) REFERENCES `gibbonInterventionPlan` (`gibbonInterventionPlanID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Part 3 - Needs
$moduleTables[] = "CREATE TABLE `gibbonInterventionNeed` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Part 4 - SMART Goals
$moduleTables[] = "CREATE TABLE `gibbonInterventionGoal` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Part 5 - Strategies
$moduleTables[] = "CREATE TABLE `gibbonInterventionStrategy` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Part 6 - Monitoring
$moduleTables[] = "CREATE TABLE `gibbonInterventionMonitoring` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Part 7 - Parent Involvement
$moduleTables[] = "CREATE TABLE `gibbonInterventionParentInvolvement` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Part 8 - Consultations
$moduleTables[] = "CREATE TABLE `gibbonInterventionConsultation` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Progress tracking
$moduleTables[] = "CREATE TABLE `gibbonInterventionProgress` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Version history tracking
$moduleTables[] = "CREATE TABLE `gibbonInterventionVersion` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// gibbonSetting entries
$gibbonSetting = [];

$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Intervention Plans', 'defaultReviewSchedule', 'Default Review Schedule', 'Default schedule for plan reviews', 'Quarterly');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Intervention Plans', 'requireParentSignature', 'Require Parent Signature', 'Require parent signature before plan becomes active', 'Y');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Intervention Plans', 'reviewReminderDays', 'Review Reminder Days', 'Days before review date to send reminder notifications', '14');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Intervention Plans', 'aiServiceIntegration', 'AI Service Integration', 'Enable integration with AI service for plan analysis', 'Y');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Intervention Plans', 'aiServiceBaseURL', 'AI Service Base URL', 'Base URL for the AI service API', 'http://localhost:8000/api/v1');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Intervention Plans', 'autoSyncWithAIService', 'Auto Sync with AI Service', 'Automatically sync plans with AI service on save', 'Y');";

// Action rows for gibbonAction
$actionRows = [];

$actionRows[] = [
    'name'                      => 'View Intervention Plans',
    'precedence'                => 0,
    'category'                  => 'Intervention Plans',
    'description'               => 'View all intervention plans and their status.',
    'URLList'                   => 'interventionPlans.php',
    'entryURL'                  => 'interventionPlans.php',
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
    'name'                      => 'Add Intervention Plan',
    'precedence'                => 0,
    'category'                  => 'Intervention Plans',
    'description'               => 'Create a new intervention plan with the 8-part structure.',
    'URLList'                   => 'interventionPlans_add.php,interventionPlans_addProcess.php',
    'entryURL'                  => 'interventionPlans_add.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Edit Intervention Plan',
    'precedence'                => 0,
    'category'                  => 'Intervention Plans',
    'description'               => 'Edit existing intervention plans.',
    'URLList'                   => 'interventionPlans_edit.php,interventionPlans_editProcess.php',
    'entryURL'                  => 'interventionPlans_edit.php',
    'entrySidebar'              => 'N',
    'menuShow'                  => 'N',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'View Intervention Plan Details',
    'precedence'                => 0,
    'category'                  => 'Intervention Plans',
    'description'               => 'View full intervention plan details including all 8 sections.',
    'URLList'                   => 'interventionPlans_view.php',
    'entryURL'                  => 'interventionPlans_view.php',
    'entrySidebar'              => 'N',
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
    'name'                      => 'Record Progress',
    'precedence'                => 0,
    'category'                  => 'Intervention Plans',
    'description'               => 'Record progress updates for intervention plan goals.',
    'URLList'                   => 'interventionPlans_progress.php,interventionPlans_progressProcess.php',
    'entryURL'                  => 'interventionPlans_progress.php',
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
    'name'                      => 'Pending Reviews',
    'precedence'                => 0,
    'category'                  => 'Intervention Plans',
    'description'               => 'View plans due or overdue for review.',
    'URLList'                   => 'interventionPlans_pendingReview.php',
    'entryURL'                  => 'interventionPlans_pendingReview.php',
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
    'name'                      => 'Module Settings',
    'precedence'                => 0,
    'category'                  => 'Intervention Plans',
    'description'               => 'Configure intervention plan module settings.',
    'URLList'                   => 'settings.php',
    'entryURL'                  => 'settings.php',
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
