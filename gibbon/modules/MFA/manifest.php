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
$name        = 'MFA';
$description = 'Multi-Factor Authentication for admin and director accounts using TOTP (Google Authenticator, Authy), backup codes, and IP whitelist.';
$entryURL    = 'mfa_settings.php';
$type        = 'Additional';
$category    = 'Security';
$version     = '1.0.00';
$author      = 'LAYA';
$url         = 'https://laya.ca';

// Module tables & gibbonSetting entries
$moduleTables = [];

$moduleTables[] = "CREATE TABLE `gibbonMFASettings` (
    `gibbonMFASettingsID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `mfaMethod` ENUM('totp','backup_code') NOT NULL DEFAULT 'totp',
    `totpSecret` VARCHAR(255) NULL COMMENT 'Encrypted TOTP secret key',
    `totpSecretEncrypted` TEXT NULL COMMENT 'Encrypted TOTP secret for storage',
    `isEnabled` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `isVerified` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `enabledAt` DATETIME NULL,
    `lastUsedAt` DATETIME NULL,
    `failedAttempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `lockedUntil` DATETIME NULL COMMENT 'Account lockout timestamp',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `isEnabled` (`isEnabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$moduleTables[] = "CREATE TABLE `gibbonMFABackupCode` (
    `gibbonMFABackupCodeID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `codeHash` VARCHAR(255) NOT NULL COMMENT 'Hashed backup code',
    `isUsed` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `usedAt` DATETIME NULL,
    `usedIP` VARCHAR(45) NULL COMMENT 'IP address when code was used',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `isUsed` (`isUsed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$moduleTables[] = "CREATE TABLE `gibbonMFAIPWhitelist` (
    `gibbonMFAIPWhitelistID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `ipAddress` VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6 address',
    `description` VARCHAR(255) NULL COMMENT 'Description of this IP (e.g., Office, Home)',
    `isActive` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `lastAccessAt` DATETIME NULL,
    `addedByID` INT UNSIGNED NOT NULL COMMENT 'Admin who added this IP',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `ipAddress` (`ipAddress`),
    KEY `isActive` (`isActive`),
    UNIQUE KEY `person_ip` (`gibbonPersonID`, `ipAddress`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

$moduleTables[] = "CREATE TABLE `gibbonMFAAuditLog` (
    `gibbonMFAAuditLogID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `action` ENUM('setup_initiated','setup_completed','setup_cancelled','enabled','disabled','verified','verification_failed','backup_codes_generated','backup_code_used','ip_whitelist_added','ip_whitelist_removed','lockout','lockout_cleared') NOT NULL,
    `ipAddress` VARCHAR(45) NULL,
    `userAgent` TEXT NULL,
    `details` TEXT NULL COMMENT 'Additional JSON details',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `action` (`action`),
    KEY `timestampCreated` (`timestampCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// gibbonSetting entries
$gibbonSetting = [];

$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('MFA', 'mfaRequired', 'MFA Required for Admins', 'Require MFA for all administrator accounts', 'Y');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('MFA', 'mfaRequiredDirectors', 'MFA Required for Directors', 'Require MFA for all director accounts', 'Y');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('MFA', 'sessionTimeout', 'Session Timeout (minutes)', 'Session timeout after inactivity in minutes', '15');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('MFA', 'maxFailedAttempts', 'Max Failed Attempts', 'Maximum failed MFA attempts before lockout', '5');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('MFA', 'lockoutDuration', 'Lockout Duration (minutes)', 'Duration of lockout after max failed attempts', '30');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('MFA', 'backupCodesCount', 'Backup Codes Count', 'Number of backup codes to generate', '10');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('MFA', 'ipWhitelistEnabled', 'IP Whitelist Enabled', 'Allow MFA bypass for whitelisted IPs', 'Y');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('MFA', 'totpIssuer', 'TOTP Issuer Name', 'Issuer name displayed in authenticator apps', 'LAYA');";

// Action rows for gibbonAction
$actionRows = [];

$actionRows[] = [
    'name'                      => 'MFA Settings',
    'precedence'                => 0,
    'category'                  => 'Security',
    'description'               => 'Manage your multi-factor authentication settings.',
    'URLList'                   => 'mfa_settings.php',
    'entryURL'                  => 'mfa_settings.php',
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
    'name'                      => 'MFA Setup',
    'precedence'                => 0,
    'category'                  => 'Security',
    'description'               => 'Set up multi-factor authentication for your account.',
    'URLList'                   => 'mfa_setup.php',
    'entryURL'                  => 'mfa_setup.php',
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
    'name'                      => 'MFA Verify',
    'precedence'                => 0,
    'category'                  => 'Security',
    'description'               => 'Verify MFA code during login.',
    'URLList'                   => 'mfa_verify.php',
    'entryURL'                  => 'mfa_verify.php',
    'entrySidebar'              => 'N',
    'menuShow'                  => 'N',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'Y',
    'defaultPermissionParent'   => 'Y',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'Y',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'Y',
];

$actionRows[] = [
    'name'                      => 'MFA Backup Codes',
    'precedence'                => 0,
    'category'                  => 'Security',
    'description'               => 'View and regenerate MFA backup codes.',
    'URLList'                   => 'mfa_backup_codes.php',
    'entryURL'                  => 'mfa_backup_codes.php',
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
    'name'                      => 'Manage MFA',
    'precedence'                => 1,
    'category'                  => 'Security',
    'description'               => 'Manage MFA settings for all users (admin only).',
    'URLList'                   => 'mfa_manage.php,mfa_manage_user.php',
    'entryURL'                  => 'mfa_manage.php',
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
    'name'                      => 'MFA IP Whitelist',
    'precedence'                => 0,
    'category'                  => 'Security',
    'description'               => 'Manage trusted IP addresses for MFA bypass.',
    'URLList'                   => 'mfa_ip_whitelist.php',
    'entryURL'                  => 'mfa_ip_whitelist.php',
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
