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
 * InstallationDetector
 *
 * Detects fresh installation and manages setup wizard state.
 * Checks various indicators to determine if this is a new installation
 * that requires the setup wizard to run.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class InstallationDetector
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
     * Constructor.
     *
     * @param SettingGateway $settingGateway Settings gateway
     * @param \PDO $pdo Database connection
     */
    public function __construct(SettingGateway $settingGateway, \PDO $pdo)
    {
        $this->settingGateway = $settingGateway;
        $this->pdo = $pdo;
    }

    /**
     * Check if this is a fresh installation requiring setup wizard.
     *
     * A fresh installation is detected when:
     * 1. The 'freshInstallation' setting is 'Y', OR
     * 2. The setup wizard has not been completed, AND
     * 3. No organization data exists (empty gibbonSchool table)
     *
     * @return bool True if fresh installation
     */
    public function isFreshInstallation()
    {
        // Check the fresh installation flag
        $freshInstall = $this->settingGateway->getSettingByScope('System', 'freshInstallation');
        if ($freshInstall === 'Y') {
            return true;
        }

        // Check if wizard was already completed
        if ($this->isWizardCompleted()) {
            return false;
        }

        // Check if organization data exists
        if ($this->hasOrganizationData()) {
            return false;
        }

        // Check if any admin users exist
        if ($this->hasAdminUsers()) {
            return false;
        }

        // No organization data and no admin users = fresh installation
        return true;
    }

    /**
     * Check if the setup wizard has been completed.
     *
     * @return bool True if wizard completed
     */
    public function isWizardCompleted()
    {
        $completed = $this->settingGateway->getSettingByScope('System', 'setupWizardCompleted');
        return $completed === 'Y';
    }

    /**
     * Check if the setup wizard is enabled.
     *
     * @return bool True if enabled
     */
    public function isWizardEnabled()
    {
        $enabled = $this->settingGateway->getSettingByScope('System', 'setupWizardEnabled');
        return $enabled === 'Y';
    }

    /**
     * Check if organization data has been configured.
     * This checks if the gibbonSchool table has any records.
     *
     * @return bool True if organization data exists
     */
    protected function hasOrganizationData()
    {
        try {
            // Check if gibbonSchool table has any records
            $stmt = $this->pdo->query("SELECT COUNT(*) FROM gibbonSchool");
            $count = (int) $stmt->fetchColumn();
            return $count > 0;
        } catch (\PDOException $e) {
            // If table doesn't exist, definitely a fresh install
            return false;
        }
    }

    /**
     * Check if any admin users exist in the system.
     *
     * @return bool True if admin users exist
     */
    protected function hasAdminUsers()
    {
        try {
            // Check if any users with admin role exist
            $stmt = $this->pdo->query("
                SELECT COUNT(*)
                FROM gibbonPerson
                WHERE status = 'Full'
                AND gibbonRoleIDPrimary IN (
                    SELECT gibbonRoleID
                    FROM gibbonRole
                    WHERE category = 'Staff'
                    AND (name = 'Administrator' OR name LIKE '%Admin%')
                )
            ");
            $count = (int) $stmt->fetchColumn();
            return $count > 0;
        } catch (\PDOException $e) {
            // If table doesn't exist or query fails, assume no admin users
            return false;
        }
    }

    /**
     * Mark the setup wizard as completed.
     *
     * @return bool True if successful
     */
    public function markWizardCompleted()
    {
        try {
            // Update the setup wizard completed flag
            $stmt = $this->pdo->prepare("
                UPDATE gibbonSetting
                SET value = 'Y'
                WHERE scope = 'System' AND name = 'setupWizardCompleted'
            ");
            $stmt->execute();

            // Update fresh installation flag
            $stmt = $this->pdo->prepare("
                UPDATE gibbonSetting
                SET value = 'N'
                WHERE scope = 'System' AND name = 'freshInstallation'
            ");
            $stmt->execute();

            // Update wizard record if exists
            $stmt = $this->pdo->prepare("
                UPDATE gibbonSetupWizard
                SET wizardCompleted = 'Y',
                    stepCompleted = 'completed'
                WHERE gibbonSetupWizardID = (
                    SELECT MAX(gibbonSetupWizardID) FROM gibbonSetupWizard
                )
            ");
            $stmt->execute();

            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Reset the wizard (mark as not completed).
     * Use with caution - this allows the wizard to run again.
     *
     * @return bool True if successful
     */
    public function resetWizard()
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE gibbonSetting
                SET value = 'N'
                WHERE scope = 'System' AND name = 'setupWizardCompleted'
            ");
            $stmt->execute();

            $stmt = $this->pdo->prepare("
                UPDATE gibbonSetting
                SET value = 'Y'
                WHERE scope = 'System' AND name = 'freshInstallation'
            ");
            $stmt->execute();

            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Get the current wizard step progress.
     *
     * @return array|null Wizard progress data or null if not started
     */
    public function getWizardProgress()
    {
        try {
            $stmt = $this->pdo->query("
                SELECT * FROM gibbonSetupWizard
                ORDER BY gibbonSetupWizardID DESC
                LIMIT 1
            ");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($result && isset($result['stepData'])) {
                $result['stepData'] = json_decode($result['stepData'], true);
            }

            return $result ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Save wizard step progress.
     *
     * @param string $step Step identifier
     * @param array $data Step data
     * @return bool True if successful
     */
    public function saveWizardProgress($step, array $data = [])
    {
        try {
            // Check if a wizard record exists
            $existing = $this->getWizardProgress();

            if ($existing) {
                // Update existing record
                $stmt = $this->pdo->prepare("
                    UPDATE gibbonSetupWizard
                    SET stepCompleted = :step,
                        stepData = :data
                    WHERE gibbonSetupWizardID = :id
                ");
                $stmt->execute([
                    ':step' => $step,
                    ':data' => json_encode($data),
                    ':id' => $existing['gibbonSetupWizardID'],
                ]);
            } else {
                // Create new record
                $stmt = $this->pdo->prepare("
                    INSERT INTO gibbonSetupWizard
                    (stepCompleted, stepData, wizardCompleted)
                    VALUES (:step, :data, 'N')
                ");
                $stmt->execute([
                    ':step' => $step,
                    ':data' => json_encode($data),
                ]);
            }

            return true;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Get installation status information.
     *
     * @return array Status information
     */
    public function getInstallationStatus()
    {
        return [
            'isFresh' => $this->isFreshInstallation(),
            'wizardCompleted' => $this->isWizardCompleted(),
            'wizardEnabled' => $this->isWizardEnabled(),
            'hasOrganization' => $this->hasOrganizationData(),
            'hasAdminUsers' => $this->hasAdminUsers(),
            'wizardProgress' => $this->getWizardProgress(),
        ];
    }

    /**
     * Check if wizard should be shown to the user.
     * Wizard is shown when:
     * - It's a fresh installation AND
     * - Wizard is enabled AND
     * - Wizard not completed
     *
     * @return bool True if wizard should be shown
     */
    public function shouldShowWizard()
    {
        return $this->isFreshInstallation()
            && $this->isWizardEnabled()
            && !$this->isWizardCompleted();
    }
}
