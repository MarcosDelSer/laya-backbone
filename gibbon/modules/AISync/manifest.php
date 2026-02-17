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
$name        = 'AI Sync';
$description = 'Synchronize data between Gibbon and AI service via webhooks.';
$entryURL    = 'aiSync.php';
$type        = 'Additional';
$category    = 'Integration';
$version     = '1.0.00';
$author      = 'LAYA';
$url         = 'https://laya.ca';

// Module tables & gibbonSetting entries
$moduleTables = [];

$moduleTables[] = "CREATE TABLE `gibbonAISyncLog` (
    `gibbonAISyncLogID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `eventType` VARCHAR(50) NOT NULL COMMENT 'Type of event: care_activity_created, meal_logged, etc.',
    `entityType` VARCHAR(50) NOT NULL COMMENT 'Entity being synced: activity, meal, nap, etc.',
    `entityID` INT UNSIGNED NOT NULL COMMENT 'ID of the entity in source table',
    `payload` JSON NULL COMMENT 'Full data payload sent to AI service',
    `status` ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
    `response` TEXT NULL COMMENT 'Response from AI service',
    `retryCount` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of retry attempts',
    `errorMessage` TEXT NULL COMMENT 'Error message if failed',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampProcessed` DATETIME NULL COMMENT 'When the sync was processed',
    KEY `eventType` (`eventType`),
    KEY `entityType` (`entityType`),
    KEY `status` (`status`),
    KEY `timestampCreated` (`timestampCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// gibbonSetting entries
$gibbonSetting = [];

$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('AI Sync', 'aiServiceURL', 'AI Service URL', 'Base URL for the AI service API', 'http://ai-service:8000');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('AI Sync', 'syncEnabled', 'Sync Enabled', 'Enable or disable AI sync functionality', 'Y');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('AI Sync', 'maxRetryAttempts', 'Max Retry Attempts', 'Maximum number of retry attempts for failed syncs', '3');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('AI Sync', 'retryDelaySeconds', 'Retry Delay (Seconds)', 'Base delay in seconds before retrying a failed sync (exponential backoff applied)', '30');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('AI Sync', 'webhookTimeout', 'Webhook Timeout', 'Timeout in seconds for webhook HTTP calls', '30');";

// Action rows for gibbonAction
$actionRows = [];

$actionRows[] = [
    'name'                      => 'AI Sync Dashboard',
    'precedence'                => 0,
    'category'                  => 'Integration',
    'description'               => 'View AI sync status and logs.',
    'URLList'                   => 'aiSync.php',
    'entryURL'                  => 'aiSync.php',
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
    'name'                      => 'AI Sync Settings',
    'precedence'                => 0,
    'category'                  => 'Integration',
    'description'               => 'Configure AI sync settings and connection parameters.',
    'URLList'                   => 'aiSync_settings.php',
    'entryURL'                  => 'aiSync_settings.php',
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
    'name'                      => 'Webhook Health Monitoring',
    'precedence'                => 0,
    'category'                  => 'Integration',
    'description'               => 'Monitor webhook health, performance metrics, and system status.',
    'URLList'                   => 'aiSync_health.php',
    'entryURL'                  => 'aiSync_health.php',
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
    'name'                      => 'View Sync Logs',
    'precedence'                => 0,
    'category'                  => 'Integration',
    'description'               => 'View detailed sync logs and troubleshoot failed syncs.',
    'URLList'                   => 'aiSync_logs.php',
    'entryURL'                  => 'aiSync_logs.php',
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
    'name'                      => 'Retry Failed Syncs',
    'precedence'                => 0,
    'category'                  => 'Integration',
    'description'               => 'Manually retry failed sync operations.',
    'URLList'                   => 'aiSync_retry.php',
    'entryURL'                  => 'aiSync_retry.php',
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
