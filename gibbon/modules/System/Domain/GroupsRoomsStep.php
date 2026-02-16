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

namespace Gibbon\Module\System\Domain;

use Gibbon\Domain\System\SettingGateway;

/**
 * GroupsRoomsStep
 *
 * Handles the groups/rooms setup step of the setup wizard.
 * Validates and creates age groups/rooms with capacity limits for daycare management.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class GroupsRoomsStep
{
    /**
     * @var SettingGateway
     */
    protected $settingGateway;

    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * @var InstallationDetector
     */
    protected $installationDetector;

    /**
     * Constructor.
     *
     * @param SettingGateway $settingGateway Settings gateway
     * @param \PDO $pdo Database connection
     * @param InstallationDetector $installationDetector Installation detector
     */
    public function __construct(
        SettingGateway $settingGateway,
        \PDO $pdo,
        InstallationDetector $installationDetector
    ) {
        $this->settingGateway = $settingGateway;
        $this->pdo = $pdo;
        $this->installationDetector = $installationDetector;
    }

    /**
     * Validate groups/rooms information.
     *
     * @param array $data Groups/rooms data
     * @return array Array of validation errors (empty if valid)
     */
    public function validate(array $data)
    {
        $errors = [];

        // Validate groups array
        if (empty($data['groups']) || !is_array($data['groups'])) {
            $errors['groups'] = 'At least one group/room is required';
            return $errors;
        }

        if (count($data['groups']) === 0) {
            $errors['groups'] = 'At least one group/room is required';
            return $errors;
        }

        $groupNames = [];

        foreach ($data['groups'] as $index => $group) {
            // Validate group name
            if (empty($group['name'])) {
                $errors["groups.{$index}.name"] = 'Group name is required';
            } elseif (strlen($group['name']) < 2) {
                $errors["groups.{$index}.name"] = 'Group name must be at least 2 characters';
            } elseif (strlen($group['name']) > 100) {
                $errors["groups.{$index}.name"] = 'Group name must not exceed 100 characters';
            } elseif (in_array(strtolower($group['name']), array_map('strtolower', $groupNames))) {
                $errors["groups.{$index}.name"] = 'Group name must be unique';
            } else {
                $groupNames[] = $group['name'];
            }

            // Validate age range
            if (isset($group['minAge']) && $group['minAge'] !== '' && $group['minAge'] !== null) {
                if (!is_numeric($group['minAge'])) {
                    $errors["groups.{$index}.minAge"] = 'Minimum age must be a number';
                } elseif ((int)$group['minAge'] < 0) {
                    $errors["groups.{$index}.minAge"] = 'Minimum age must be 0 or greater';
                } elseif ((int)$group['minAge'] > 18) {
                    $errors["groups.{$index}.minAge"] = 'Minimum age must not exceed 18 years';
                }
            }

            if (isset($group['maxAge']) && $group['maxAge'] !== '' && $group['maxAge'] !== null) {
                if (!is_numeric($group['maxAge'])) {
                    $errors["groups.{$index}.maxAge"] = 'Maximum age must be a number';
                } elseif ((int)$group['maxAge'] < 0) {
                    $errors["groups.{$index}.maxAge"] = 'Maximum age must be 0 or greater';
                } elseif ((int)$group['maxAge'] > 18) {
                    $errors["groups.{$index}.maxAge"] = 'Maximum age must not exceed 18 years';
                }
            }

            // Validate that minAge <= maxAge
            if (
                isset($group['minAge']) && $group['minAge'] !== '' && $group['minAge'] !== null &&
                isset($group['maxAge']) && $group['maxAge'] !== '' && $group['maxAge'] !== null
            ) {
                if ((int)$group['minAge'] > (int)$group['maxAge']) {
                    $errors["groups.{$index}.ageRange"] = 'Minimum age must be less than or equal to maximum age';
                }
            }

            // Validate capacity
            if (empty($group['capacity'])) {
                $errors["groups.{$index}.capacity"] = 'Capacity is required';
            } elseif (!is_numeric($group['capacity'])) {
                $errors["groups.{$index}.capacity"] = 'Capacity must be a number';
            } elseif ((int)$group['capacity'] < 1) {
                $errors["groups.{$index}.capacity"] = 'Capacity must be at least 1';
            } elseif ((int)$group['capacity'] > 999) {
                $errors["groups.{$index}.capacity"] = 'Capacity must not exceed 999';
            }

            // Validate description (optional)
            if (isset($group['description']) && strlen($group['description']) > 500) {
                $errors["groups.{$index}.description"] = 'Description must not exceed 500 characters';
            }
        }

        return $errors;
    }

    /**
     * Save groups/rooms to the database.
     *
     * @param array $data Groups/rooms data
     * @return bool True if successful
     */
    public function save(array $data)
    {
        try {
            // Validate data first
            $errors = $this->validate($data);
            if (!empty($errors)) {
                return false;
            }

            // Begin transaction
            $this->pdo->beginTransaction();

            try {
                // Ensure the table exists
                $this->createGroupsTable();

                // Clear existing groups
                $this->clearGroups();

                // Insert new groups
                foreach ($data['groups'] as $group) {
                    $this->addGroup($group);
                }

                // Save progress in wizard
                $this->installationDetector->saveWizardProgress('groups_rooms', $data);

                $this->pdo->commit();
                return true;
            } catch (\Exception $e) {
                $this->pdo->rollBack();
                return false;
            }
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Create groups table if it doesn't exist.
     *
     * @return bool True if successful
     */
    protected function createGroupsTable()
    {
        try {
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS gibbonDaycareGroup (
                    gibbonDaycareGroupID INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    name VARCHAR(100) NOT NULL,
                    description VARCHAR(500),
                    minAge INT UNSIGNED,
                    maxAge INT UNSIGNED,
                    capacity INT UNSIGNED NOT NULL,
                    isActive ENUM('Y', 'N') NOT NULL DEFAULT 'Y',
                    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (gibbonDaycareGroupID),
                    UNIQUE KEY idx_name (name),
                    INDEX idx_active (isActive)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * Clear all groups.
     *
     * @return bool True if successful
     */
    protected function clearGroups()
    {
        try {
            // Check if table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'gibbonDaycareGroup'");
            if (!$stmt->fetchColumn()) {
                return true;
            }

            $stmt = $this->pdo->prepare("DELETE FROM gibbonDaycareGroup");
            $stmt->execute();
            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * Add a group to the database.
     *
     * @param array $group Group data
     * @return bool True if successful
     */
    protected function addGroup(array $group)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO gibbonDaycareGroup (name, description, minAge, maxAge, capacity, isActive)
                VALUES (:name, :description, :minAge, :maxAge, :capacity, :isActive)
            ");

            $minAge = isset($group['minAge']) && $group['minAge'] !== '' ? (int)$group['minAge'] : null;
            $maxAge = isset($group['maxAge']) && $group['maxAge'] !== '' ? (int)$group['maxAge'] : null;
            $description = isset($group['description']) ? $group['description'] : null;
            $isActive = isset($group['isActive']) ? $group['isActive'] : 'Y';

            $stmt->execute([
                ':name' => $group['name'],
                ':description' => $description,
                ':minAge' => $minAge,
                ':maxAge' => $maxAge,
                ':capacity' => (int)$group['capacity'],
                ':isActive' => $isActive,
            ]);

            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * Get saved groups/rooms information.
     *
     * @return array|null Groups/rooms data or null if not found
     */
    public function getGroupsRooms()
    {
        try {
            // Check if table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'gibbonDaycareGroup'");
            if (!$stmt->fetchColumn()) {
                return ['groups' => []];
            }

            $stmt = $this->pdo->query("
                SELECT name, description, minAge, maxAge, capacity, isActive
                FROM gibbonDaycareGroup
                ORDER BY name ASC
            ");

            $groups = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            return ['groups' => $groups];
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Check if groups/rooms have been configured.
     *
     * @return bool True if groups/rooms configured
     */
    public function isCompleted()
    {
        try {
            // Check if table exists
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'gibbonDaycareGroup'");
            if (!$stmt->fetchColumn()) {
                return false;
            }

            $stmt = $this->pdo->query("SELECT COUNT(*) FROM gibbonDaycareGroup");
            $count = (int) $stmt->fetchColumn();
            return $count > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Get groups/rooms from wizard progress (for resume capability).
     *
     * @return array|null Groups/rooms data from wizard progress
     */
    public function getWizardProgress()
    {
        $progress = $this->installationDetector->getWizardProgress();

        if ($progress && isset($progress['stepData']) && is_array($progress['stepData'])) {
            return $progress['stepData'];
        }

        return null;
    }

    /**
     * Prepare groups/rooms data for display/editing.
     * Merges saved data with wizard progress.
     *
     * @return array Groups/rooms data
     */
    public function prepareData()
    {
        // Start with saved groups/rooms
        $data = $this->getGroupsRooms() ?: ['groups' => []];

        // Override with wizard progress if available (for resume)
        $wizardData = $this->getWizardProgress();
        if ($wizardData) {
            $data = array_merge($data, $wizardData);
        }

        return $data;
    }

    /**
     * Clear groups/rooms (for testing/reset).
     *
     * @return bool True if successful
     */
    public function clear()
    {
        try {
            $this->pdo->beginTransaction();

            try {
                $this->clearGroups();
                $this->pdo->commit();
                return true;
            } catch (\PDOException $e) {
                $this->pdo->rollBack();
                return false;
            }
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Get default groups/rooms (sample data).
     *
     * @return array Default groups
     */
    public function getDefaultGroups()
    {
        return [
            'groups' => [
                [
                    'name' => 'Infants',
                    'description' => '0-12 months',
                    'minAge' => 0,
                    'maxAge' => 1,
                    'capacity' => 8,
                    'isActive' => 'Y',
                ],
                [
                    'name' => 'Toddlers',
                    'description' => '1-3 years',
                    'minAge' => 1,
                    'maxAge' => 3,
                    'capacity' => 12,
                    'isActive' => 'Y',
                ],
                [
                    'name' => 'Preschool',
                    'description' => '3-5 years',
                    'minAge' => 3,
                    'maxAge' => 5,
                    'capacity' => 20,
                    'isActive' => 'Y',
                ],
            ],
        ];
    }
}
