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
$name        = 'Care Tracking';
$description = 'Daily care tracking for childcare: attendance, meals, naps, diapers, incidents, and activities.';
$entryURL    = 'careTracking.php';
$type        = 'Additional';
$category    = 'Childcare';
$version     = '1.0.00';
$author      = 'LAYA';
$url         = 'https://laya.ca';

// Module tables & gibbonSetting entries
$moduleTables = [];

$moduleTables[] = "CREATE TABLE `gibbonCareAttendance` (
    `gibbonCareAttendanceID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `checkInTime` TIME NULL,
    `checkOutTime` TIME NULL,
    `checkInByID` INT UNSIGNED NULL COMMENT 'Staff who checked in',
    `checkOutByID` INT UNSIGNED NULL COMMENT 'Staff who checked out',
    `pickupPersonName` VARCHAR(100) NULL COMMENT 'Name of authorized pickup person',
    `notes` TEXT NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$moduleTables[] = "CREATE TABLE `gibbonCareMeal` (
    `gibbonCareMealID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `mealType` ENUM('Breakfast','Morning Snack','Lunch','Afternoon Snack','Dinner') NOT NULL,
    `quantity` ENUM('None','Little','Some','Most','All') NOT NULL DEFAULT 'Some',
    `notes` TEXT NULL,
    `recordedByID` INT UNSIGNED NOT NULL COMMENT 'Staff who recorded',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `date` (`date`),
    KEY `mealType` (`mealType`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$moduleTables[] = "CREATE TABLE `gibbonCareNap` (
    `gibbonCareNapID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `startTime` TIME NOT NULL,
    `endTime` TIME NULL,
    `quality` ENUM('Restless','Light','Sound') NULL,
    `notes` TEXT NULL,
    `recordedByID` INT UNSIGNED NOT NULL COMMENT 'Staff who recorded',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$moduleTables[] = "CREATE TABLE `gibbonCareDiaper` (
    `gibbonCareDiaperID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `time` TIME NOT NULL,
    `type` ENUM('Wet','Soiled','Both','Dry') NOT NULL,
    `notes` TEXT NULL,
    `recordedByID` INT UNSIGNED NOT NULL COMMENT 'Staff who recorded',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$moduleTables[] = "CREATE TABLE `gibbonCareIncident` (
    `gibbonCareIncidentID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `time` TIME NOT NULL,
    `type` ENUM('Minor Injury','Major Injury','Illness','Behavioral','Other') NOT NULL,
    `description` TEXT NOT NULL,
    `actionTaken` TEXT NULL,
    `parentNotified` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `parentNotifiedTime` DATETIME NULL,
    `parentAcknowledged` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `parentAcknowledgedTime` DATETIME NULL,
    `recordedByID` INT UNSIGNED NOT NULL COMMENT 'Staff who recorded',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `date` (`date`),
    KEY `type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$moduleTables[] = "CREATE TABLE `gibbonCareActivity` (
    `gibbonCareActivityID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `activityName` VARCHAR(100) NOT NULL,
    `activityType` ENUM('Art','Music','Physical','Language','Math','Science','Social','Free Play','Outdoor','Other') NOT NULL,
    `duration` INT UNSIGNED NULL COMMENT 'Duration in minutes',
    `participation` ENUM('Not Interested','Observing','Participating','Leading') NULL,
    `notes` TEXT NULL,
    `recordedByID` INT UNSIGNED NOT NULL COMMENT 'Staff who recorded',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `date` (`date`),
    KEY `activityType` (`activityType`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// gibbonSetting entries
$gibbonSetting = [];

$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Care Tracking', 'mealTypes', 'Meal Types', 'Comma-separated list of meal types to track', 'Breakfast,Morning Snack,Lunch,Afternoon Snack,Dinner');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Care Tracking', 'napMinDuration', 'Minimum Nap Duration', 'Minimum nap duration in minutes to display alert', '30');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Care Tracking', 'incidentNotifyParent', 'Auto-notify Parent on Incident', 'Automatically send notification to parents when incident is logged', 'Y');";

// Action rows for gibbonAction
$actionRows = [];

$actionRows[] = [
    'name'                      => 'Care Tracking Dashboard',
    'precedence'                => 0,
    'category'                  => 'Care',
    'description'               => 'View daily care tracking dashboard for all children.',
    'URLList'                   => 'careTracking.php',
    'entryURL'                  => 'careTracking.php',
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
    'name'                      => 'Attendance Check-In/Out',
    'precedence'                => 0,
    'category'                  => 'Care',
    'description'               => 'Check children in and out for the day.',
    'URLList'                   => 'careTracking_attendance.php',
    'entryURL'                  => 'careTracking_attendance.php',
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
    'name'                      => 'Meal Logging',
    'precedence'                => 0,
    'category'                  => 'Care',
    'description'               => 'Log meals and snacks for children.',
    'URLList'                   => 'careTracking_meals.php',
    'entryURL'                  => 'careTracking_meals.php',
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
    'name'                      => 'Nap Tracking',
    'precedence'                => 0,
    'category'                  => 'Care',
    'description'               => 'Track nap times and quality for children.',
    'URLList'                   => 'careTracking_naps.php',
    'entryURL'                  => 'careTracking_naps.php',
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
    'name'                      => 'Diaper Tracking',
    'precedence'                => 0,
    'category'                  => 'Care',
    'description'               => 'Track diaper changes for infants and toddlers.',
    'URLList'                   => 'careTracking_diapers.php',
    'entryURL'                  => 'careTracking_diapers.php',
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
    'name'                      => 'Incident Reports',
    'precedence'                => 0,
    'category'                  => 'Care',
    'description'               => 'Log and manage incident reports.',
    'URLList'                   => 'careTracking_incidents.php',
    'entryURL'                  => 'careTracking_incidents.php',
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
    'name'                      => 'Activity Logging',
    'precedence'                => 0,
    'category'                  => 'Care',
    'description'               => 'Log activities and participation for children.',
    'URLList'                   => 'careTracking_activities.php',
    'entryURL'                  => 'careTracking_activities.php',
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

// Menu Management Actions
$actionRows[] = [
    'name'                      => 'Menu Items Management',
    'precedence'                => 0,
    'category'                  => 'Menu',
    'description'               => 'Manage menu items with allergens and nutritional information.',
    'URLList'                   => 'menu_items.php,menu_items_add.php,menu_items_edit.php',
    'entryURL'                  => 'menu_items.php',
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
    'name'                      => 'Weekly Menu Editor',
    'precedence'                => 0,
    'category'                  => 'Menu',
    'description'               => 'Plan and schedule weekly menus for each meal type.',
    'URLList'                   => 'menu_weekly.php,menu_weekly_edit.php',
    'entryURL'                  => 'menu_weekly.php',
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
    'name'                      => 'Child Dietary Profiles',
    'precedence'                => 0,
    'category'                  => 'Menu',
    'description'               => 'View and manage dietary profiles and allergies for children.',
    'URLList'                   => 'child_dietary.php,child_dietary_edit.php',
    'entryURL'                  => 'child_dietary.php',
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
    'name'                      => 'Menu Reports',
    'precedence'                => 0,
    'category'                  => 'Menu',
    'description'               => 'View nutritional summaries and allergen exposure reports.',
    'URLList'                   => 'menu_reports.php',
    'entryURL'                  => 'menu_reports.php',
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

// Note: Daily Report and Settings actions will be added in a future phase
// when report_daily.php and settings.php are implemented

// Hooks (optional - for extending existing pages)
$hooks = [];
