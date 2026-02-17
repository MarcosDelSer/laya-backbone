<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuiber and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Gibbon Core) and Gibbon LAYA are trademarks of Gibbon Education Ltd.

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

// Module metadata
$name        = 'Notification Engine';
$description = 'Multi-channel notification system for LAYA kindergarten supporting email (via Gibbon Mailer) and Firebase Cloud Messaging push notifications.';
$entryURL    = 'notifications_queue.php';
$type        = 'Additional';
$category    = 'Admin';
$version     = '1.0.00';
$author      = 'LAYA Development Team';
$url         = 'https://laya.education';

// Module tables
$moduleTables = [
    'gibbonNotificationQueue',
    'gibbonNotificationTemplate',
    'gibbonNotificationPreference',
    'gibbonFCMToken',
];

// Module actions (menu items)
$actionRows = [];

$actionRows[0] = [
    'name'                      => 'Manage Notification Queue',
    'precedence'                => '0',
    'category'                  => 'Notifications',
    'description'               => 'View and manage the notification queue',
    'URLList'                   => 'notifications_queue.php',
    'entryURL'                  => 'notifications_queue.php',
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

$actionRows[1] = [
    'name'                      => 'Notification Settings',
    'precedence'                => '0',
    'category'                  => 'Notifications',
    'description'               => 'Configure your notification preferences per type and channel',
    'URLList'                   => 'notifications_settings.php',
    'entryURL'                  => 'notifications_settings.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
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

$actionRows[2] = [
    'name'                      => 'Manage Templates',
    'precedence'                => '0',
    'category'                  => 'Notifications',
    'description'               => 'Manage notification templates for different event types',
    'URLList'                   => 'notifications_templates.php',
    'entryURL'                  => 'notifications_templates.php',
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

// Module settings
$gibbonSetting = [];

$gibbonSetting[0] = [
    'scope'       => 'Notification Engine',
    'name'        => 'fcmEnabled',
    'nameDisplay' => 'Firebase Push Notifications',
    'description' => 'Enable Firebase Cloud Messaging (FCM) push notifications',
    'value'       => 'Y',
];

$gibbonSetting[1] = [
    'scope'       => 'Notification Engine',
    'name'        => 'emailEnabled',
    'nameDisplay' => 'Email Notifications',
    'description' => 'Enable email notifications using Gibbon Mailer',
    'value'       => 'Y',
];

$gibbonSetting[2] = [
    'scope'       => 'Notification Engine',
    'name'        => 'maxRetryAttempts',
    'nameDisplay' => 'Max Retry Attempts',
    'description' => 'Maximum number of retry attempts for failed notifications',
    'value'       => '3',
];

$gibbonSetting[3] = [
    'scope'       => 'Notification Engine',
    'name'        => 'queueBatchSize',
    'nameDisplay' => 'Queue Batch Size',
    'description' => 'Number of notifications to process per queue run',
    'value'       => '50',
];

$gibbonSetting[4] = [
    'scope'       => 'Notification Engine',
    'name'        => 'retryDelayMinutes',
    'nameDisplay' => 'Retry Delay (Minutes)',
    'description' => 'Base delay in minutes before retrying a failed notification (exponential backoff applied)',
    'value'       => '5',
];
