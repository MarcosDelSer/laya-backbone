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

// MFA Module - Database Change Log
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = array();
$count = 0;

// v1.0.00 - Initial release
++$count;
$sql[$count][0] = '1.0.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonMFASettings` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonMFABackupCode` (
    `gibbonMFABackupCodeID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `codeHash` VARCHAR(255) NOT NULL COMMENT 'Hashed backup code',
    `isUsed` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `usedAt` DATETIME NULL,
    `usedIP` VARCHAR(45) NULL COMMENT 'IP address when code was used',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `isUsed` (`isUsed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonMFAIPWhitelist` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonMFAAuditLog` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('MFA', 'mfaRequired', 'MFA Required for Admins', 'Require MFA for all administrator accounts', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('MFA', 'mfaRequiredDirectors', 'MFA Required for Directors', 'Require MFA for all director accounts', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('MFA', 'sessionTimeout', 'Session Timeout (minutes)', 'Session timeout after inactivity in minutes', '15') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('MFA', 'maxFailedAttempts', 'Max Failed Attempts', 'Maximum failed MFA attempts before lockout', '5') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('MFA', 'lockoutDuration', 'Lockout Duration (minutes)', 'Duration of lockout after max failed attempts', '30') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('MFA', 'backupCodesCount', 'Backup Codes Count', 'Number of backup codes to generate', '10') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('MFA', 'ipWhitelistEnabled', 'IP Whitelist Enabled', 'Allow MFA bypass for whitelisted IPs', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('MFA', 'totpIssuer', 'TOTP Issuer Name', 'Issuer name displayed in authenticator apps', 'LAYA') ON DUPLICATE KEY UPDATE scope=scope;end
";

// v1.0.01 - Bug fixes and minor improvements
++$count;
$sql[$count][0] = '1.0.01';
$sql[$count][1] = "";

// v1.1.00 - Emergency recovery and enhanced audit logging
++$count;
$sql[$count][0] = '1.1.00';
$sql[$count][1] = "
ALTER TABLE `gibbonMFASettings` ADD COLUMN `recoveryEmail` VARCHAR(255) NULL COMMENT 'Recovery email for emergency MFA reset' AFTER `lockedUntil`;end
ALTER TABLE `gibbonMFASettings` ADD COLUMN `recoveryEmailVerified` ENUM('Y','N') NOT NULL DEFAULT 'N' AFTER `recoveryEmail`;end
ALTER TABLE `gibbonMFASettings` ADD COLUMN `lastRecoveryAt` DATETIME NULL AFTER `recoveryEmailVerified`;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('MFA', 'emergencyRecoveryEnabled', 'Emergency Recovery Enabled', 'Allow emergency recovery via verified email', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('MFA', 'emergencyRecoveryCooldown', 'Emergency Recovery Cooldown (hours)', 'Minimum hours between emergency recovery requests', '24') ON DUPLICATE KEY UPDATE scope=scope;end
";

// v1.2.00 - Device trust and remember me functionality
++$count;
$sql[$count][0] = '1.2.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonMFATrustedDevice` (
    `gibbonMFATrustedDeviceID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `deviceToken` VARCHAR(255) NOT NULL COMMENT 'Hashed device token',
    `deviceName` VARCHAR(100) NULL COMMENT 'User-friendly device name',
    `lastIPAddress` VARCHAR(45) NULL,
    `lastUserAgent` TEXT NULL,
    `lastAccessAt` DATETIME NULL,
    `expiresAt` DATETIME NOT NULL,
    `isActive` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `deviceToken` (`deviceToken`),
    KEY `isActive` (`isActive`),
    KEY `expiresAt` (`expiresAt`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('MFA', 'trustedDeviceEnabled', 'Trusted Device Enabled', 'Allow users to trust devices for MFA bypass', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('MFA', 'trustedDeviceDays', 'Trusted Device Days', 'Number of days a trusted device remains valid', '30') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('MFA', 'maxTrustedDevices', 'Max Trusted Devices', 'Maximum number of trusted devices per user', '5') ON DUPLICATE KEY UPDATE scope=scope;end
";
