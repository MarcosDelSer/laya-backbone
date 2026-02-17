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

namespace Gibbon\Module\MFA\Domain;

use Gibbon\Domain\Traits\TableAware;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Domain\QueryableGateway;

/**
 * MFAGateway
 *
 * Gateway for MFA settings, backup codes, IP whitelist, audit log, and trusted device operations.
 * Supports TOTP-based multi-factor authentication with backup code recovery and IP whitelist bypass.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class MFAGateway extends QueryableGateway
{
    use TableAware;

    private static $tableName = 'gibbonMFASettings';
    private static $primaryKey = 'gibbonMFASettingsID';
    private static $searchableColumns = [];

    // =========================================================================
    // MFA SETTINGS OPERATIONS
    // =========================================================================

    /**
     * Query MFA settings with pagination support.
     *
     * @param QueryCriteria $criteria
     * @param string|null $isEnabled Filter by enabled status (Y/N)
     * @return \Gibbon\Domain\DataSet
     */
    public function queryMFASettings(QueryCriteria $criteria, $isEnabled = null)
    {
        $query = $this
            ->newQuery()
            ->from($this->getTableName())
            ->cols([
                'gibbonMFASettings.gibbonMFASettingsID',
                'gibbonMFASettings.gibbonPersonID',
                'gibbonMFASettings.mfaMethod',
                'gibbonMFASettings.isEnabled',
                'gibbonMFASettings.isVerified',
                'gibbonMFASettings.enabledAt',
                'gibbonMFASettings.lastUsedAt',
                'gibbonMFASettings.failedAttempts',
                'gibbonMFASettings.lockedUntil',
                'gibbonMFASettings.timestampCreated',
                'gibbonMFASettings.timestampModified',
                'person.preferredName AS userPreferredName',
                'person.surname AS userSurname',
                'person.email AS userEmail',
                'person.username AS username',
            ])
            ->leftJoin('gibbonPerson AS person', 'person.gibbonPersonID = gibbonMFASettings.gibbonPersonID');

        if ($isEnabled !== null) {
            $query->where('gibbonMFASettings.isEnabled = :isEnabled')
                  ->bindValue('isEnabled', $isEnabled);
        }

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get MFA settings for a specific user.
     *
     * @param int $gibbonPersonID
     * @return array|false
     */
    public function getMFASettingsByPerson($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT s.*,
                       p.preferredName AS userPreferredName,
                       p.surname AS userSurname,
                       p.email AS userEmail,
                       p.username AS username
                FROM gibbonMFASettings s
                LEFT JOIN gibbonPerson AS p ON p.gibbonPersonID = s.gibbonPersonID
                WHERE s.gibbonPersonID = :gibbonPersonID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Get MFA settings by ID.
     *
     * @param int $gibbonMFASettingsID
     * @return array|false
     */
    public function getMFASettingsByID($gibbonMFASettingsID)
    {
        $data = ['gibbonMFASettingsID' => $gibbonMFASettingsID];
        $sql = "SELECT s.*,
                       p.preferredName AS userPreferredName,
                       p.surname AS userSurname,
                       p.email AS userEmail,
                       p.username AS username
                FROM gibbonMFASettings s
                LEFT JOIN gibbonPerson AS p ON p.gibbonPersonID = s.gibbonPersonID
                WHERE s.gibbonMFASettingsID = :gibbonMFASettingsID";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Check if MFA is enabled for a user.
     *
     * @param int $gibbonPersonID
     * @return bool
     */
    public function isMFAEnabled($gibbonPersonID)
    {
        $settings = $this->getMFASettingsByPerson($gibbonPersonID);
        return $settings && $settings['isEnabled'] === 'Y' && $settings['isVerified'] === 'Y';
    }

    /**
     * Check if user account is locked due to failed MFA attempts.
     *
     * @param int $gibbonPersonID
     * @return bool
     */
    public function isAccountLocked($gibbonPersonID)
    {
        $settings = $this->getMFASettingsByPerson($gibbonPersonID);
        if (!$settings || empty($settings['lockedUntil'])) {
            return false;
        }
        return strtotime($settings['lockedUntil']) > time();
    }

    /**
     * Insert new MFA settings for a user.
     *
     * @param array $data MFA settings data
     * @return int|false The new settings ID or false on failure
     */
    public function insertMFASettings(array $data)
    {
        $fields = [
            'gibbonPersonID',
            'mfaMethod',
            'totpSecret',
            'totpSecretEncrypted',
            'isEnabled',
            'isVerified',
            'enabledAt',
            'recoveryEmail',
            'recoveryEmailVerified',
        ];

        $insertData = array_intersect_key($data, array_flip($fields));

        // Default values
        if (!isset($insertData['mfaMethod'])) {
            $insertData['mfaMethod'] = 'totp';
        }
        if (!isset($insertData['isEnabled'])) {
            $insertData['isEnabled'] = 'N';
        }
        if (!isset($insertData['isVerified'])) {
            $insertData['isVerified'] = 'N';
        }

        return $this->insert($insertData);
    }

    /**
     * Update MFA settings for a user.
     *
     * @param int $gibbonPersonID
     * @param array $data Updated settings
     * @return bool
     */
    public function updateMFASettings($gibbonPersonID, array $data)
    {
        $allowedFields = [
            'mfaMethod',
            'totpSecret',
            'totpSecretEncrypted',
            'isEnabled',
            'isVerified',
            'enabledAt',
            'lastUsedAt',
            'failedAttempts',
            'lockedUntil',
            'recoveryEmail',
            'recoveryEmailVerified',
            'lastRecoveryAt',
        ];

        $updateData = array_intersect_key($data, array_flip($allowedFields));
        $updateData['gibbonPersonID'] = $gibbonPersonID;

        $sets = [];
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $updateData)) {
                $sets[] = "$field = :$field";
            }
        }

        if (empty($sets)) {
            return false;
        }

        $sql = "UPDATE gibbonMFASettings
                SET " . implode(', ', $sets) . "
                WHERE gibbonPersonID = :gibbonPersonID";

        return $this->db()->statement($sql, $updateData);
    }

    /**
     * Enable MFA for a user.
     *
     * @param int $gibbonPersonID
     * @return bool
     */
    public function enableMFA($gibbonPersonID)
    {
        return $this->updateMFASettings($gibbonPersonID, [
            'isEnabled' => 'Y',
            'isVerified' => 'Y',
            'enabledAt' => date('Y-m-d H:i:s'),
            'failedAttempts' => 0,
            'lockedUntil' => null,
        ]);
    }

    /**
     * Disable MFA for a user.
     *
     * @param int $gibbonPersonID
     * @return bool
     */
    public function disableMFA($gibbonPersonID)
    {
        return $this->updateMFASettings($gibbonPersonID, [
            'isEnabled' => 'N',
            'isVerified' => 'N',
            'totpSecret' => null,
            'totpSecretEncrypted' => null,
            'failedAttempts' => 0,
            'lockedUntil' => null,
        ]);
    }

    /**
     * Increment failed attempts and lock account if threshold reached.
     *
     * @param int $gibbonPersonID
     * @param int $maxAttempts Maximum allowed attempts before lockout
     * @param int $lockoutMinutes Duration of lockout in minutes
     * @return array ['locked' => bool, 'attempts' => int]
     */
    public function incrementFailedAttempts($gibbonPersonID, $maxAttempts = 5, $lockoutMinutes = 30)
    {
        $settings = $this->getMFASettingsByPerson($gibbonPersonID);
        if (!$settings) {
            return ['locked' => false, 'attempts' => 0];
        }

        $newAttempts = (int) $settings['failedAttempts'] + 1;
        $lockedUntil = null;
        $locked = false;

        if ($newAttempts >= $maxAttempts) {
            $lockedUntil = date('Y-m-d H:i:s', strtotime("+{$lockoutMinutes} minutes"));
            $locked = true;
        }

        $this->updateMFASettings($gibbonPersonID, [
            'failedAttempts' => $newAttempts,
            'lockedUntil' => $lockedUntil,
        ]);

        return ['locked' => $locked, 'attempts' => $newAttempts];
    }

    /**
     * Reset failed attempts after successful verification.
     *
     * @param int $gibbonPersonID
     * @return bool
     */
    public function resetFailedAttempts($gibbonPersonID)
    {
        return $this->updateMFASettings($gibbonPersonID, [
            'failedAttempts' => 0,
            'lockedUntil' => null,
            'lastUsedAt' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Clear account lockout (admin action).
     *
     * @param int $gibbonPersonID
     * @return bool
     */
    public function clearLockout($gibbonPersonID)
    {
        return $this->updateMFASettings($gibbonPersonID, [
            'failedAttempts' => 0,
            'lockedUntil' => null,
        ]);
    }

    /**
     * Delete MFA settings for a user.
     *
     * @param int $gibbonPersonID
     * @return bool
     */
    public function deleteMFASettings($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "DELETE FROM gibbonMFASettings WHERE gibbonPersonID = :gibbonPersonID";
        return $this->db()->delete($sql, $data) > 0;
    }

    // =========================================================================
    // BACKUP CODE OPERATIONS
    // =========================================================================

    /**
     * Get backup codes for a user.
     *
     * @param int $gibbonPersonID
     * @param bool $unusedOnly Only return unused codes
     * @return array
     */
    public function selectBackupCodesByPerson($gibbonPersonID, $unusedOnly = false)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT * FROM gibbonMFABackupCode
                WHERE gibbonPersonID = :gibbonPersonID";

        if ($unusedOnly) {
            $sql .= " AND isUsed = 'N'";
        }

        $sql .= " ORDER BY timestampCreated ASC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Count unused backup codes for a user.
     *
     * @param int $gibbonPersonID
     * @return int
     */
    public function countUnusedBackupCodes($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT COUNT(*) as count FROM gibbonMFABackupCode
                WHERE gibbonPersonID = :gibbonPersonID
                AND isUsed = 'N'";

        $result = $this->db()->selectOne($sql, $data);
        return $result ? (int) $result['count'] : 0;
    }

    /**
     * Insert a new backup code.
     *
     * @param int $gibbonPersonID
     * @param string $codeHash Hashed backup code
     * @return int|false
     */
    public function insertBackupCode($gibbonPersonID, $codeHash)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'codeHash' => $codeHash,
            'isUsed' => 'N',
        ];
        $sql = "INSERT INTO gibbonMFABackupCode (gibbonPersonID, codeHash, isUsed)
                VALUES (:gibbonPersonID, :codeHash, :isUsed)";

        $this->db()->statement($sql, $data);
        return $this->db()->getConnection()->lastInsertID();
    }

    /**
     * Insert multiple backup codes at once.
     *
     * @param int $gibbonPersonID
     * @param array $codeHashes Array of hashed backup codes
     * @return int Number of codes inserted
     */
    public function insertBackupCodes($gibbonPersonID, array $codeHashes)
    {
        $count = 0;
        foreach ($codeHashes as $codeHash) {
            $result = $this->insertBackupCode($gibbonPersonID, $codeHash);
            if ($result !== false) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Verify a backup code.
     *
     * @param int $gibbonPersonID
     * @param string $codeHash Hashed code to verify
     * @return array|false The backup code record or false if not found/used
     */
    public function verifyBackupCode($gibbonPersonID, $codeHash)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'codeHash' => $codeHash,
        ];
        $sql = "SELECT * FROM gibbonMFABackupCode
                WHERE gibbonPersonID = :gibbonPersonID
                AND codeHash = :codeHash
                AND isUsed = 'N'";

        return $this->db()->selectOne($sql, $data);
    }

    /**
     * Mark a backup code as used.
     *
     * @param int $gibbonMFABackupCodeID
     * @param string|null $usedIP IP address when code was used
     * @return bool
     */
    public function markBackupCodeUsed($gibbonMFABackupCodeID, $usedIP = null)
    {
        $data = [
            'gibbonMFABackupCodeID' => $gibbonMFABackupCodeID,
            'usedIP' => $usedIP,
        ];
        $sql = "UPDATE gibbonMFABackupCode
                SET isUsed = 'Y',
                    usedAt = NOW(),
                    usedIP = :usedIP
                WHERE gibbonMFABackupCodeID = :gibbonMFABackupCodeID";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Delete all backup codes for a user (before regenerating).
     *
     * @param int $gibbonPersonID
     * @return int Number of codes deleted
     */
    public function deleteBackupCodes($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "DELETE FROM gibbonMFABackupCode WHERE gibbonPersonID = :gibbonPersonID";
        return $this->db()->delete($sql, $data);
    }

    // =========================================================================
    // IP WHITELIST OPERATIONS
    // =========================================================================

    /**
     * Query IP whitelist with pagination support.
     *
     * @param QueryCriteria $criteria
     * @param int|null $gibbonPersonID Filter by user
     * @return \Gibbon\Domain\DataSet
     */
    public function queryIPWhitelist(QueryCriteria $criteria, $gibbonPersonID = null)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonMFAIPWhitelist')
            ->cols([
                'gibbonMFAIPWhitelist.gibbonMFAIPWhitelistID',
                'gibbonMFAIPWhitelist.gibbonPersonID',
                'gibbonMFAIPWhitelist.ipAddress',
                'gibbonMFAIPWhitelist.description',
                'gibbonMFAIPWhitelist.isActive',
                'gibbonMFAIPWhitelist.lastAccessAt',
                'gibbonMFAIPWhitelist.addedByID',
                'gibbonMFAIPWhitelist.timestampCreated',
                'person.preferredName AS userPreferredName',
                'person.surname AS userSurname',
                'addedBy.preferredName AS addedByPreferredName',
                'addedBy.surname AS addedBySurname',
            ])
            ->leftJoin('gibbonPerson AS person', 'person.gibbonPersonID = gibbonMFAIPWhitelist.gibbonPersonID')
            ->leftJoin('gibbonPerson AS addedBy', 'addedBy.gibbonPersonID = gibbonMFAIPWhitelist.addedByID');

        if ($gibbonPersonID !== null) {
            $query->where('gibbonMFAIPWhitelist.gibbonPersonID = :gibbonPersonID')
                  ->bindValue('gibbonPersonID', $gibbonPersonID);
        }

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get active whitelisted IPs for a user.
     *
     * @param int $gibbonPersonID
     * @return array
     */
    public function selectWhitelistedIPsByPerson($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT * FROM gibbonMFAIPWhitelist
                WHERE gibbonPersonID = :gibbonPersonID
                AND isActive = 'Y'
                ORDER BY timestampCreated DESC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Check if an IP address is whitelisted for a user.
     *
     * @param int $gibbonPersonID
     * @param string $ipAddress
     * @return bool
     */
    public function isIPWhitelisted($gibbonPersonID, $ipAddress)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'ipAddress' => $ipAddress,
        ];
        $sql = "SELECT gibbonMFAIPWhitelistID FROM gibbonMFAIPWhitelist
                WHERE gibbonPersonID = :gibbonPersonID
                AND ipAddress = :ipAddress
                AND isActive = 'Y'";

        $result = $this->db()->selectOne($sql, $data);

        if ($result) {
            // Update last access timestamp
            $this->updateIPWhitelistAccess($result['gibbonMFAIPWhitelistID']);
            return true;
        }

        return false;
    }

    /**
     * Add an IP address to the whitelist.
     *
     * @param int $gibbonPersonID User to whitelist for
     * @param string $ipAddress IP address
     * @param string|null $description Description of this IP
     * @param int $addedByID Admin who added this IP
     * @return int|false
     */
    public function insertIPWhitelist($gibbonPersonID, $ipAddress, $description, $addedByID)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'ipAddress' => $ipAddress,
            'description' => $description,
            'addedByID' => $addedByID,
            'isActive' => 'Y',
        ];
        $sql = "INSERT INTO gibbonMFAIPWhitelist
                (gibbonPersonID, ipAddress, description, addedByID, isActive)
                VALUES (:gibbonPersonID, :ipAddress, :description, :addedByID, :isActive)
                ON DUPLICATE KEY UPDATE
                    description = :description,
                    addedByID = :addedByID,
                    isActive = 'Y'";

        $this->db()->statement($sql, $data);
        return $this->db()->getConnection()->lastInsertID();
    }

    /**
     * Update last access timestamp for a whitelisted IP.
     *
     * @param int $gibbonMFAIPWhitelistID
     * @return bool
     */
    public function updateIPWhitelistAccess($gibbonMFAIPWhitelistID)
    {
        $data = ['gibbonMFAIPWhitelistID' => $gibbonMFAIPWhitelistID];
        $sql = "UPDATE gibbonMFAIPWhitelist
                SET lastAccessAt = NOW()
                WHERE gibbonMFAIPWhitelistID = :gibbonMFAIPWhitelistID";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Deactivate a whitelisted IP.
     *
     * @param int $gibbonMFAIPWhitelistID
     * @return bool
     */
    public function deactivateIPWhitelist($gibbonMFAIPWhitelistID)
    {
        $data = ['gibbonMFAIPWhitelistID' => $gibbonMFAIPWhitelistID];
        $sql = "UPDATE gibbonMFAIPWhitelist
                SET isActive = 'N'
                WHERE gibbonMFAIPWhitelistID = :gibbonMFAIPWhitelistID";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Delete a whitelisted IP.
     *
     * @param int $gibbonMFAIPWhitelistID
     * @return bool
     */
    public function deleteIPWhitelist($gibbonMFAIPWhitelistID)
    {
        $data = ['gibbonMFAIPWhitelistID' => $gibbonMFAIPWhitelistID];
        $sql = "DELETE FROM gibbonMFAIPWhitelist WHERE gibbonMFAIPWhitelistID = :gibbonMFAIPWhitelistID";
        return $this->db()->delete($sql, $data) > 0;
    }

    // =========================================================================
    // AUDIT LOG OPERATIONS
    // =========================================================================

    /**
     * Query MFA audit log with pagination support.
     *
     * @param QueryCriteria $criteria
     * @param int|null $gibbonPersonID Filter by user
     * @param string|null $action Filter by action type
     * @return \Gibbon\Domain\DataSet
     */
    public function queryAuditLog(QueryCriteria $criteria, $gibbonPersonID = null, $action = null)
    {
        $query = $this
            ->newQuery()
            ->from('gibbonMFAAuditLog')
            ->cols([
                'gibbonMFAAuditLog.gibbonMFAAuditLogID',
                'gibbonMFAAuditLog.gibbonPersonID',
                'gibbonMFAAuditLog.action',
                'gibbonMFAAuditLog.ipAddress',
                'gibbonMFAAuditLog.userAgent',
                'gibbonMFAAuditLog.details',
                'gibbonMFAAuditLog.timestampCreated',
                'person.preferredName AS userPreferredName',
                'person.surname AS userSurname',
                'person.username AS username',
            ])
            ->leftJoin('gibbonPerson AS person', 'person.gibbonPersonID = gibbonMFAAuditLog.gibbonPersonID');

        if ($gibbonPersonID !== null) {
            $query->where('gibbonMFAAuditLog.gibbonPersonID = :gibbonPersonID')
                  ->bindValue('gibbonPersonID', $gibbonPersonID);
        }

        if ($action !== null) {
            $query->where('gibbonMFAAuditLog.action = :action')
                  ->bindValue('action', $action);
        }

        return $this->runQuery($query, $criteria);
    }

    /**
     * Get recent audit log entries for a user.
     *
     * @param int $gibbonPersonID
     * @param int $limit
     * @return array
     */
    public function selectRecentAuditLog($gibbonPersonID, $limit = 20)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'limit' => (int) $limit,
        ];
        $sql = "SELECT * FROM gibbonMFAAuditLog
                WHERE gibbonPersonID = :gibbonPersonID
                ORDER BY timestampCreated DESC
                LIMIT :limit";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Insert an audit log entry.
     *
     * @param int $gibbonPersonID
     * @param string $action Action type
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param array|null $details Additional details
     * @return int|false
     */
    public function insertAuditLog($gibbonPersonID, $action, $ipAddress = null, $userAgent = null, $details = null)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'action' => $action,
            'ipAddress' => $ipAddress,
            'userAgent' => $userAgent,
            'details' => $details !== null ? json_encode($details) : null,
        ];
        $sql = "INSERT INTO gibbonMFAAuditLog
                (gibbonPersonID, action, ipAddress, userAgent, details)
                VALUES (:gibbonPersonID, :action, :ipAddress, :userAgent, :details)";

        $this->db()->statement($sql, $data);
        return $this->db()->getConnection()->lastInsertID();
    }

    /**
     * Purge old audit log entries.
     *
     * @param int $daysOld Days to keep logs
     * @return int Number of records purged
     */
    public function purgeOldAuditLogs($daysOld = 90)
    {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));

        $data = ['cutoffDate' => $cutoffDate];
        $sql = "DELETE FROM gibbonMFAAuditLog
                WHERE timestampCreated < :cutoffDate";

        return $this->db()->delete($sql, $data);
    }

    // =========================================================================
    // TRUSTED DEVICE OPERATIONS
    // =========================================================================

    /**
     * Get trusted devices for a user.
     *
     * @param int $gibbonPersonID
     * @param bool $activeOnly Only return active, non-expired devices
     * @return array
     */
    public function selectTrustedDevicesByPerson($gibbonPersonID, $activeOnly = true)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT * FROM gibbonMFATrustedDevice
                WHERE gibbonPersonID = :gibbonPersonID";

        if ($activeOnly) {
            $sql .= " AND isActive = 'Y' AND expiresAt > NOW()";
        }

        $sql .= " ORDER BY lastAccessAt DESC";

        return $this->db()->select($sql, $data)->fetchAll();
    }

    /**
     * Verify a trusted device token.
     *
     * @param int $gibbonPersonID
     * @param string $deviceToken Hashed device token
     * @return array|false The device record or false if not found/expired
     */
    public function verifyTrustedDevice($gibbonPersonID, $deviceToken)
    {
        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'deviceToken' => $deviceToken,
        ];
        $sql = "SELECT * FROM gibbonMFATrustedDevice
                WHERE gibbonPersonID = :gibbonPersonID
                AND deviceToken = :deviceToken
                AND isActive = 'Y'
                AND expiresAt > NOW()";

        $device = $this->db()->selectOne($sql, $data);

        if ($device) {
            // Update last access
            $this->updateTrustedDeviceAccess($device['gibbonMFATrustedDeviceID']);
        }

        return $device;
    }

    /**
     * Register a new trusted device.
     *
     * @param int $gibbonPersonID
     * @param string $deviceToken Hashed device token
     * @param string|null $deviceName User-friendly device name
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @param int $expiryDays Days until device trust expires
     * @return int|false
     */
    public function insertTrustedDevice($gibbonPersonID, $deviceToken, $deviceName, $ipAddress, $userAgent, $expiryDays = 30)
    {
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));

        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'deviceToken' => $deviceToken,
            'deviceName' => $deviceName,
            'lastIPAddress' => $ipAddress,
            'lastUserAgent' => $userAgent,
            'expiresAt' => $expiresAt,
            'isActive' => 'Y',
        ];
        $sql = "INSERT INTO gibbonMFATrustedDevice
                (gibbonPersonID, deviceToken, deviceName, lastIPAddress, lastUserAgent, lastAccessAt, expiresAt, isActive)
                VALUES (:gibbonPersonID, :deviceToken, :deviceName, :lastIPAddress, :lastUserAgent, NOW(), :expiresAt, :isActive)";

        $this->db()->statement($sql, $data);
        return $this->db()->getConnection()->lastInsertID();
    }

    /**
     * Update last access for a trusted device.
     *
     * @param int $gibbonMFATrustedDeviceID
     * @param string|null $ipAddress
     * @param string|null $userAgent
     * @return bool
     */
    public function updateTrustedDeviceAccess($gibbonMFATrustedDeviceID, $ipAddress = null, $userAgent = null)
    {
        $data = ['gibbonMFATrustedDeviceID' => $gibbonMFATrustedDeviceID];
        $sets = ['lastAccessAt = NOW()'];

        if ($ipAddress !== null) {
            $data['lastIPAddress'] = $ipAddress;
            $sets[] = 'lastIPAddress = :lastIPAddress';
        }
        if ($userAgent !== null) {
            $data['lastUserAgent'] = $userAgent;
            $sets[] = 'lastUserAgent = :lastUserAgent';
        }

        $sql = "UPDATE gibbonMFATrustedDevice
                SET " . implode(', ', $sets) . "
                WHERE gibbonMFATrustedDeviceID = :gibbonMFATrustedDeviceID";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Revoke a trusted device.
     *
     * @param int $gibbonMFATrustedDeviceID
     * @return bool
     */
    public function revokeTrustedDevice($gibbonMFATrustedDeviceID)
    {
        $data = ['gibbonMFATrustedDeviceID' => $gibbonMFATrustedDeviceID];
        $sql = "UPDATE gibbonMFATrustedDevice
                SET isActive = 'N'
                WHERE gibbonMFATrustedDeviceID = :gibbonMFATrustedDeviceID";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Revoke all trusted devices for a user.
     *
     * @param int $gibbonPersonID
     * @return bool
     */
    public function revokeAllTrustedDevices($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "UPDATE gibbonMFATrustedDevice
                SET isActive = 'N'
                WHERE gibbonPersonID = :gibbonPersonID";

        return $this->db()->statement($sql, $data);
    }

    /**
     * Delete a trusted device.
     *
     * @param int $gibbonMFATrustedDeviceID
     * @return bool
     */
    public function deleteTrustedDevice($gibbonMFATrustedDeviceID)
    {
        $data = ['gibbonMFATrustedDeviceID' => $gibbonMFATrustedDeviceID];
        $sql = "DELETE FROM gibbonMFATrustedDevice WHERE gibbonMFATrustedDeviceID = :gibbonMFATrustedDeviceID";
        return $this->db()->delete($sql, $data) > 0;
    }

    /**
     * Count active trusted devices for a user.
     *
     * @param int $gibbonPersonID
     * @return int
     */
    public function countTrustedDevices($gibbonPersonID)
    {
        $data = ['gibbonPersonID' => $gibbonPersonID];
        $sql = "SELECT COUNT(*) as count FROM gibbonMFATrustedDevice
                WHERE gibbonPersonID = :gibbonPersonID
                AND isActive = 'Y'
                AND expiresAt > NOW()";

        $result = $this->db()->selectOne($sql, $data);
        return $result ? (int) $result['count'] : 0;
    }

    /**
     * Cleanup expired trusted devices.
     *
     * @return int Number of devices cleaned up
     */
    public function cleanupExpiredTrustedDevices()
    {
        $sql = "DELETE FROM gibbonMFATrustedDevice
                WHERE expiresAt < NOW()
                OR isActive = 'N'";

        return $this->db()->delete($sql, []);
    }

    // =========================================================================
    // STATISTICS & REPORTING
    // =========================================================================

    /**
     * Get MFA adoption statistics.
     *
     * @return array
     */
    public function getMFAStatistics()
    {
        $sql = "SELECT
                    COUNT(*) as total_users_with_mfa,
                    SUM(CASE WHEN isEnabled = 'Y' AND isVerified = 'Y' THEN 1 ELSE 0 END) as enabled_count,
                    SUM(CASE WHEN isEnabled = 'N' THEN 1 ELSE 0 END) as disabled_count,
                    SUM(CASE WHEN lockedUntil > NOW() THEN 1 ELSE 0 END) as locked_count
                FROM gibbonMFASettings";

        return $this->db()->selectOne($sql, []);
    }

    /**
     * Get users requiring MFA setup (admins/directors without MFA enabled).
     *
     * @param array $roleIDs Role IDs that require MFA
     * @return array
     */
    public function selectUsersRequiringMFA(array $roleIDs)
    {
        if (empty($roleIDs)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($roleIDs), '?'));
        $sql = "SELECT p.gibbonPersonID, p.preferredName, p.surname, p.email, p.username,
                       s.isEnabled, s.isVerified
                FROM gibbonPerson p
                LEFT JOIN gibbonMFASettings s ON s.gibbonPersonID = p.gibbonPersonID
                WHERE p.gibbonRoleIDPrimary IN ({$placeholders})
                AND p.status = 'Full'
                AND (s.gibbonMFASettingsID IS NULL OR s.isEnabled = 'N' OR s.isVerified = 'N')
                ORDER BY p.surname, p.preferredName";

        return $this->db()->select($sql, $roleIDs)->fetchAll();
    }
}
