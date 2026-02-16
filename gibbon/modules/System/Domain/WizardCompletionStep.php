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
 * WizardCompletionStep
 *
 * Handles the final step of the setup wizard.
 * Sets the wizard completion flag to prevent the wizard from running again.
 * Validates that all required previous steps have been completed before
 * allowing the wizard to be marked as complete.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class WizardCompletionStep
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
     * Required wizard steps that must be completed
     */
    const REQUIRED_STEPS = [
        'organization_info',
        'admin_account',
        'operating_hours',
        'groups_rooms',
        'finance_settings',
        'service_connectivity',
    ];

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
     * Validate that all required wizard steps have been completed.
     *
     * @param array $data Optional data (not used for this step)
     * @return array Array of validation errors (empty if valid)
     */
    public function validate(array $data = [])
    {
        $errors = [];

        // Check that wizard is not already completed
        if ($this->isCompleted()) {
            $errors['wizard_completed'] = 'Setup wizard has already been completed';
            return $errors;
        }

        // Verify all required steps are completed
        $progress = $this->installationDetector->getWizardProgress();

        if (!$progress) {
            $errors['no_progress'] = 'No wizard progress found. Please complete all wizard steps first.';
            return $errors;
        }

        // Check each required step
        $missingSteps = [];
        foreach (self::REQUIRED_STEPS as $step) {
            if (!$this->isStepCompleted($step)) {
                $missingSteps[] = $this->formatStepName($step);
            }
        }

        if (!empty($missingSteps)) {
            $errors['incomplete_steps'] = 'The following wizard steps must be completed first: ' . implode(', ', $missingSteps);
        }

        return $errors;
    }

    /**
     * Check if a specific wizard step has been completed.
     *
     * @param string $stepName Step name to check
     * @return bool True if step is completed
     */
    protected function isStepCompleted($stepName)
    {
        $progress = $this->installationDetector->getWizardProgress();

        if (!$progress || !isset($progress['stepData']) || !is_array($progress['stepData'])) {
            return false;
        }

        // Check if the step exists and has data (indicating completion)
        return isset($progress['stepData'][$stepName]) && !empty($progress['stepData'][$stepName]);
    }

    /**
     * Format step name for display.
     *
     * @param string $stepName Step name
     * @return string Formatted step name
     */
    protected function formatStepName($stepName)
    {
        return ucwords(str_replace('_', ' ', $stepName));
    }

    /**
     * Save wizard completion flag and finalize the setup.
     *
     * @param array $data Optional completion data (timestamp, notes, etc.)
     * @return bool True if successful
     */
    public function save(array $data = [])
    {
        try {
            // Begin transaction
            $this->pdo->beginTransaction();

            try {
                // Mark wizard as completed using InstallationDetector
                if (!$this->installationDetector->markWizardCompleted()) {
                    throw new \Exception('Failed to mark wizard as completed');
                }

                // Save completion timestamp
                $this->saveSetting('System', 'setupWizardCompletedDate', date('Y-m-d H:i:s'));

                // Save any additional completion data
                if (!empty($data)) {
                    $completionData = [
                        'completed_at' => time(),
                        'data' => $data,
                    ];
                    $this->saveSetting('System', 'setupWizardCompletionData', json_encode($completionData));
                }

                // Save final wizard progress
                $this->installationDetector->saveWizardProgress('completed', [
                    'completed_at' => time(),
                    'all_steps_verified' => true,
                ]);

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
     * Save a setting to the database.
     *
     * @param string $scope Setting scope
     * @param string $name Setting name
     * @param string $value Setting value
     * @return bool True if successful
     */
    protected function saveSetting($scope, $name, $value)
    {
        try {
            // Check if setting exists
            $stmt = $this->pdo->prepare("
                SELECT gibbonSettingID FROM gibbonSetting
                WHERE scope = :scope AND name = :name
            ");
            $stmt->execute([':scope' => $scope, ':name' => $name]);
            $exists = $stmt->fetchColumn();

            if ($exists) {
                // Update existing setting
                $stmt = $this->pdo->prepare("
                    UPDATE gibbonSetting
                    SET value = :value
                    WHERE scope = :scope AND name = :name
                ");
            } else {
                // Insert new setting
                $stmt = $this->pdo->prepare("
                    INSERT INTO gibbonSetting (scope, name, value, nameDisplay, description)
                    VALUES (:scope, :name, :value, :nameDisplay, :description)
                ");
                $stmt->bindValue(':nameDisplay', ucfirst($name));
                $stmt->bindValue(':description', 'Auto-generated by setup wizard');
            }

            $stmt->bindValue(':scope', $scope);
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':value', $value);
            $stmt->execute();

            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * Check if wizard has been completed.
     *
     * @return bool True if wizard is completed
     */
    public function isCompleted()
    {
        return $this->installationDetector->isWizardCompleted();
    }

    /**
     * Get wizard completion data for display.
     *
     * @return array Completion data
     */
    public function prepareData()
    {
        $data = [
            'is_completed' => $this->isCompleted(),
            'completed_steps' => [],
            'pending_steps' => [],
        ];

        // Check each required step
        foreach (self::REQUIRED_STEPS as $step) {
            if ($this->isStepCompleted($step)) {
                $data['completed_steps'][] = $step;
            } else {
                $data['pending_steps'][] = $step;
            }
        }

        // Get completion timestamp if available
        try {
            $stmt = $this->pdo->prepare("
                SELECT value FROM gibbonSetting
                WHERE scope = 'System' AND name = 'setupWizardCompletedDate'
            ");
            $stmt->execute();
            $completedDate = $stmt->fetchColumn();

            if ($completedDate) {
                $data['completed_at'] = $completedDate;
            }
        } catch (\PDOException $e) {
            // Ignore error
        }

        // Get completion data if available
        try {
            $stmt = $this->pdo->prepare("
                SELECT value FROM gibbonSetting
                WHERE scope = 'System' AND name = 'setupWizardCompletionData'
            ");
            $stmt->execute();
            $completionDataJson = $stmt->fetchColumn();

            if ($completionDataJson) {
                $completionData = json_decode($completionDataJson, true);
                if ($completionData) {
                    $data['completion_data'] = $completionData;
                }
            }
        } catch (\PDOException $e) {
            // Ignore error
        }

        return $data;
    }

    /**
     * Get list of required wizard steps.
     *
     * @return array Required step names
     */
    public function getRequiredSteps()
    {
        return self::REQUIRED_STEPS;
    }

    /**
     * Check if wizard can be completed (all required steps done).
     *
     * @return bool True if wizard can be completed
     */
    public function canComplete()
    {
        if ($this->isCompleted()) {
            return false;
        }

        foreach (self::REQUIRED_STEPS as $step) {
            if (!$this->isStepCompleted($step)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Clear wizard completion data (for testing/reset).
     * This will allow the wizard to run again.
     *
     * @return bool True if successful
     */
    public function clear()
    {
        try {
            $this->pdo->beginTransaction();

            try {
                // Reset wizard using InstallationDetector
                if (!$this->installationDetector->resetWizard()) {
                    throw new \Exception('Failed to reset wizard');
                }

                // Delete completion-related settings
                $stmt = $this->pdo->prepare("
                    DELETE FROM gibbonSetting
                    WHERE scope = 'System' AND name IN (
                        'setupWizardCompletedDate',
                        'setupWizardCompletionData'
                    )
                ");
                $stmt->execute();

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
}
