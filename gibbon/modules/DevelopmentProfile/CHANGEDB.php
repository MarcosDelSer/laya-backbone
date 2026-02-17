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

// Development Profile Module - Database Change Log
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = array();
$count = 0;

// v1.0.00 - Initial release with Quebec-aligned developmental tracking
++$count;
$sql[$count][0] = '1.0.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonDevelopmentProfile` (
    `gibbonDevelopmentProfileID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Child ID',
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `educatorID` INT UNSIGNED NULL COMMENT 'Primary educator assigned',
    `birthDate` DATE NULL COMMENT 'Child birth date for age calculations',
    `notes` TEXT NULL,
    `isActive` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `gibbonPersonID_gibbonSchoolYearID` (`gibbonPersonID`, `gibbonSchoolYearID`),
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `gibbonSchoolYearID` (`gibbonSchoolYearID`),
    KEY `educatorID` (`educatorID`),
    KEY `isActive` (`isActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonSkillAssessment` (
    `gibbonSkillAssessmentID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonDevelopmentProfileID` INT UNSIGNED NOT NULL,
    `domain` ENUM('affective','social','language','cognitive','gross_motor','fine_motor') NOT NULL COMMENT 'Quebec developmental domain',
    `skillName` VARCHAR(200) NOT NULL COMMENT 'Skill name in English',
    `skillNameFR` VARCHAR(200) NULL COMMENT 'Skill name in French',
    `status` ENUM('can','learning','not_yet','na') NOT NULL DEFAULT 'not_yet' COMMENT 'Assessment status',
    `assessedAt` DATETIME NOT NULL COMMENT 'When skill was assessed',
    `assessedByID` INT UNSIGNED NULL COMMENT 'Staff who assessed',
    `evidence` TEXT NULL COMMENT 'Observable evidence supporting assessment',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonDevelopmentProfileID` (`gibbonDevelopmentProfileID`),
    KEY `domain` (`domain`),
    KEY `status` (`status`),
    KEY `assessedAt` (`assessedAt`),
    CONSTRAINT `fk_skillassessment_profile` FOREIGN KEY (`gibbonDevelopmentProfileID`)
        REFERENCES `gibbonDevelopmentProfile` (`gibbonDevelopmentProfileID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonDevelopmentObservation` (
    `gibbonDevelopmentObservationID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonDevelopmentProfileID` INT UNSIGNED NOT NULL,
    `domain` ENUM('affective','social','language','cognitive','gross_motor','fine_motor') NOT NULL COMMENT 'Primary developmental domain',
    `observedAt` DATETIME NOT NULL COMMENT 'When behavior was observed',
    `observerID` INT UNSIGNED NULL COMMENT 'Person who made the observation',
    `observerType` ENUM('educator','parent','specialist') NOT NULL DEFAULT 'educator',
    `behaviorDescription` TEXT NOT NULL COMMENT 'Detailed behavior description',
    `context` TEXT NULL COMMENT 'Context of observation',
    `isMilestone` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Is this a developmental milestone',
    `isConcern` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Does this raise concerns',
    `attachments` JSON NULL COMMENT 'Array of attachment references',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonDevelopmentProfileID` (`gibbonDevelopmentProfileID`),
    KEY `domain` (`domain`),
    KEY `observedAt` (`observedAt`),
    KEY `observerID` (`observerID`),
    KEY `isMilestone` (`isMilestone`),
    KEY `isConcern` (`isConcern`),
    CONSTRAINT `fk_observation_profile` FOREIGN KEY (`gibbonDevelopmentProfileID`)
        REFERENCES `gibbonDevelopmentProfile` (`gibbonDevelopmentProfileID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonDevelopmentSnapshot` (
    `gibbonDevelopmentSnapshotID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonDevelopmentProfileID` INT UNSIGNED NOT NULL,
    `snapshotMonth` DATE NOT NULL COMMENT 'First day of the snapshot month',
    `ageMonths` INT UNSIGNED NULL COMMENT 'Child age in months at snapshot',
    `domainSummaries` JSON NULL COMMENT 'Summary per domain',
    `overallProgress` ENUM('on_track','needs_support','excelling') NOT NULL DEFAULT 'on_track',
    `strengths` JSON NULL COMMENT 'List of identified strengths',
    `growthAreas` JSON NULL COMMENT 'List of areas needing growth',
    `recommendations` TEXT NULL COMMENT 'Recommendations for next period',
    `generatedByID` INT UNSIGNED NULL COMMENT 'Staff who generated/approved',
    `isParentShared` ENUM('Y','N') NOT NULL DEFAULT 'N' COMMENT 'Shared with parents',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonDevelopmentProfileID` (`gibbonDevelopmentProfileID`),
    KEY `snapshotMonth` (`snapshotMonth`),
    KEY `overallProgress` (`overallProgress`),
    KEY `isParentShared` (`isParentShared`),
    UNIQUE KEY `profile_month` (`gibbonDevelopmentProfileID`, `snapshotMonth`),
    CONSTRAINT `fk_snapshot_profile` FOREIGN KEY (`gibbonDevelopmentProfileID`)
        REFERENCES `gibbonDevelopmentProfile` (`gibbonDevelopmentProfileID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Development Profile', 'developmentDomains', 'Development Domains', 'Comma-separated list of Quebec developmental domains', 'affective,social,language,cognitive,gross_motor,fine_motor') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Development Profile', 'skillStatuses', 'Skill Statuses', 'Comma-separated list of skill assessment statuses', 'can,learning,not_yet,na') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Development Profile', 'snapshotNotifyParent', 'Auto-notify Parent on Snapshot', 'Automatically send notification to parents when monthly snapshot is generated', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Development Profile', 'concernAlertEducator', 'Alert Educator on Concern', 'Alert primary educator when an observation is marked as concern', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
";
