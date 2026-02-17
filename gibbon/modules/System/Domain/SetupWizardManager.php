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
 * SetupWizardManager
 *
 * Orchestrates the setup wizard flow and manages resume capability.
 * This class coordinates all wizard steps and allows users to resume
 * an interrupted wizard from the last incomplete step.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class SetupWizardManager
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
     * @var array Wizard step definitions in order
     */
    protected $steps = [
        'organization_info' => [
            'id' => 'organization_info',
            'name' => 'Organization Information',
            'class' => 'OrganizationInfoStep',
            'required' => true,
        ],
        'admin_account' => [
            'id' => 'admin_account',
            'name' => 'Administrator Account',
            'class' => 'AdminAccountCreationStep',
            'required' => true,
        ],
        'operating_hours' => [
            'id' => 'operating_hours',
            'name' => 'Operating Hours',
            'class' => 'OperatingHoursStep',
            'required' => true,
        ],
        'groups_rooms' => [
            'id' => 'groups_rooms',
            'name' => 'Groups and Rooms',
            'class' => 'GroupsRoomsStep',
            'required' => true,
        ],
        'finance_settings' => [
            'id' => 'finance_settings',
            'name' => 'Finance Settings',
            'class' => 'FinanceSettingsStep',
            'required' => true,
        ],
        'service_connectivity' => [
            'id' => 'service_connectivity',
            'name' => 'Service Connectivity',
            'class' => 'ServiceConnectivityStep',
            'required' => true,
        ],
        'sample_data' => [
            'id' => 'sample_data',
            'name' => 'Sample Data Import',
            'class' => 'SampleDataImportStep',
            'required' => false,
        ],
        'completion' => [
            'id' => 'completion',
            'name' => 'Wizard Completion',
            'class' => 'WizardCompletionStep',
            'required' => true,
        ],
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
     * Get all wizard steps.
     *
     * @return array Array of step definitions
     */
    public function getSteps()
    {
        return $this->steps;
    }

    /**
     * Get a specific step definition.
     *
     * @param string $stepId Step identifier
     * @return array|null Step definition or null if not found
     */
    public function getStep($stepId)
    {
        return $this->steps[$stepId] ?? null;
    }

    /**
     * Get the current step to resume from.
     * Returns the first incomplete step, or null if wizard is complete.
     *
     * @return array|null Step definition with completion status
     */
    public function getCurrentStep()
    {
        // Check if wizard is already completed
        if ($this->installationDetector->isWizardCompleted()) {
            return null;
        }

        // Get wizard progress
        $progress = $this->installationDetector->getWizardProgress();
        $completedSteps = $this->getCompletedSteps();

        // Find the first incomplete step
        foreach ($this->steps as $stepId => $step) {
            if (!in_array($stepId, $completedSteps)) {
                return array_merge($step, [
                    'isCompleted' => false,
                    'canAccess' => $this->canAccessStep($stepId),
                    'data' => $this->getStepData($stepId),
                ]);
            }
        }

        // All steps completed - return completion step
        return array_merge($this->steps['completion'], [
            'isCompleted' => false,
            'canAccess' => true,
            'data' => [],
        ]);
    }

    /**
     * Get the next step after the current one.
     *
     * @param string $currentStepId Current step identifier
     * @return array|null Next step definition or null if at end
     */
    public function getNextStep($currentStepId)
    {
        $stepIds = array_keys($this->steps);
        $currentIndex = array_search($currentStepId, $stepIds);

        if ($currentIndex === false || $currentIndex >= count($stepIds) - 1) {
            return null;
        }

        $nextStepId = $stepIds[$currentIndex + 1];
        return array_merge($this->steps[$nextStepId], [
            'isCompleted' => $this->isStepCompleted($nextStepId),
            'canAccess' => $this->canAccessStep($nextStepId),
            'data' => $this->getStepData($nextStepId),
        ]);
    }

    /**
     * Get the previous step before the current one.
     *
     * @param string $currentStepId Current step identifier
     * @return array|null Previous step definition or null if at start
     */
    public function getPreviousStep($currentStepId)
    {
        $stepIds = array_keys($this->steps);
        $currentIndex = array_search($currentStepId, $stepIds);

        if ($currentIndex === false || $currentIndex <= 0) {
            return null;
        }

        $prevStepId = $stepIds[$currentIndex - 1];
        return array_merge($this->steps[$prevStepId], [
            'isCompleted' => $this->isStepCompleted($prevStepId),
            'canAccess' => true, // Can always go back
            'data' => $this->getStepData($prevStepId),
        ]);
    }

    /**
     * Check if a step is completed.
     *
     * @param string $stepId Step identifier
     * @return bool True if step is completed
     */
    public function isStepCompleted($stepId)
    {
        $completedSteps = $this->getCompletedSteps();
        return in_array($stepId, $completedSteps);
    }

    /**
     * Check if a step can be accessed.
     * A step can be accessed if all previous required steps are completed.
     *
     * @param string $stepId Step identifier
     * @return bool True if step can be accessed
     */
    public function canAccessStep($stepId)
    {
        $completedSteps = $this->getCompletedSteps();
        $stepIds = array_keys($this->steps);
        $targetIndex = array_search($stepId, $stepIds);

        if ($targetIndex === false) {
            return false;
        }

        // Check all previous required steps are completed
        for ($i = 0; $i < $targetIndex; $i++) {
            $prevStepId = $stepIds[$i];
            $prevStep = $this->steps[$prevStepId];

            if ($prevStep['required'] && !in_array($prevStepId, $completedSteps)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get list of completed step IDs.
     *
     * Checks both the individual step completion flags (settings) AND
     * the stepData from wizard progress. A step is considered complete
     * if either source indicates completion, ensuring consistency with
     * WizardCompletionStep::isStepCompleted().
     *
     * @return array Array of completed step IDs
     */
    public function getCompletedSteps()
    {
        $completed = [];

        // Get stepData from wizard progress for fallback checks
        $progress = $this->installationDetector->getWizardProgress();
        $stepData = ($progress && isset($progress['stepData']) && is_array($progress['stepData']))
            ? $progress['stepData']
            : [];

        // Check organization_info
        if ($this->isOrganizationInfoCompleted() || $this->hasStepData($stepData, 'organization_info')) {
            $completed[] = 'organization_info';
        }

        // Check admin_account
        if ($this->isAdminAccountCompleted() || $this->hasStepData($stepData, 'admin_account')) {
            $completed[] = 'admin_account';
        }

        // Check operating_hours
        if ($this->isOperatingHoursCompleted() || $this->hasStepData($stepData, 'operating_hours')) {
            $completed[] = 'operating_hours';
        }

        // Check groups_rooms
        if ($this->isGroupsRoomsCompleted() || $this->hasStepData($stepData, 'groups_rooms')) {
            $completed[] = 'groups_rooms';
        }

        // Check finance_settings
        if ($this->isFinanceSettingsCompleted() || $this->hasStepData($stepData, 'finance_settings')) {
            $completed[] = 'finance_settings';
        }

        // Check service_connectivity
        if ($this->isServiceConnectivityCompleted() || $this->hasStepData($stepData, 'service_connectivity')) {
            $completed[] = 'service_connectivity';
        }

        // Check sample_data (optional)
        if ($this->isSampleDataCompleted() || $this->hasStepData($stepData, 'sample_data')) {
            $completed[] = 'sample_data';
        }

        return $completed;
    }

    /**
     * Check if stepData contains non-empty data for a given step.
     *
     * This provides consistency with WizardCompletionStep::isStepCompleted()
     * which uses stepData as the source of truth for step completion.
     *
     * @param array $stepData The stepData array from wizard progress
     * @param string $stepId The step identifier to check
     * @return bool True if step has data indicating completion
     */
    protected function hasStepData(array $stepData, $stepId)
    {
        return isset($stepData[$stepId]) && !empty($stepData[$stepId]);
    }

    /**
     * Get saved data for a specific step.
     *
     * @param string $stepId Step identifier
     * @return array Saved step data
     */
    public function getStepData($stepId)
    {
        try {
            // Get data from wizard progress
            $progress = $this->installationDetector->getWizardProgress();
            if ($progress && isset($progress['stepData'])) {
                $stepData = is_array($progress['stepData']) ? $progress['stepData'] : [];
                return $stepData[$stepId] ?? [];
            }

            // Check settings-based storage for each step type
            $setting = $this->settingGateway->getSettingByScope('SetupWizard', $stepId);
            if ($setting) {
                $data = json_decode($setting, true);
                return is_array($data) ? $data : [];
            }

            return [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Save data for a specific step.
     *
     * Saves the step data to wizard progress AND sets the step completion flag
     * in settings. This ensures consistency between the two sources of truth
     * for step completion status.
     *
     * @param string $stepId Step identifier
     * @param array $data Step data
     * @return bool True if successful
     */
    public function saveStepData($stepId, array $data)
    {
        try {
            // Get current wizard progress
            $progress = $this->installationDetector->getWizardProgress();
            $stepData = $progress && isset($progress['stepData']) ? $progress['stepData'] : [];

            // Update step data
            $stepData[$stepId] = $data;

            // Save updated progress to wizard table
            $saved = $this->installationDetector->saveWizardProgress($stepId, $stepData);

            // Also set the completion flag in settings for consistency
            if ($saved && !empty($data)) {
                $this->markStepCompleted($stepId);
            }

            return $saved;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Mark a specific step as completed in settings.
     *
     * This sets the {step_id}_completed setting to 'Y', keeping
     * the settings-based completion flags in sync with stepData.
     *
     * @param string $stepId Step identifier
     * @return bool True if successful
     */
    protected function markStepCompleted($stepId)
    {
        try {
            // Validate step ID
            if (!isset($this->steps[$stepId])) {
                return false;
            }

            $settingName = $stepId . '_completed';

            // Check if setting exists
            $existing = $this->settingGateway->getSettingByScope('SetupWizard', $settingName);

            if ($existing !== null) {
                // Update existing setting via direct PDO since SettingGateway may not have update method
                $stmt = $this->pdo->prepare("
                    UPDATE gibbonSetting
                    SET value = 'Y'
                    WHERE scope = 'SetupWizard' AND name = :name
                ");
                $stmt->execute([':name' => $settingName]);
            } else {
                // Insert new setting
                $stmt = $this->pdo->prepare("
                    INSERT INTO gibbonSetting (scope, name, value, nameDisplay, description)
                    VALUES ('SetupWizard', :name, 'Y', :display, 'Step completion flag')
                ");
                $stmt->execute([
                    ':name' => $settingName,
                    ':display' => ucwords(str_replace('_', ' ', $stepId)) . ' Completed',
                ]);
            }

            return true;
        } catch (\Exception $e) {
            // Non-critical - stepData is the primary source of truth
            return false;
        }
    }

    /**
     * Get wizard completion progress percentage.
     *
     * @return int Completion percentage (0-100)
     */
    public function getCompletionPercentage()
    {
        $completedSteps = $this->getCompletedSteps();
        $requiredSteps = array_filter($this->steps, function ($step) {
            return $step['required'];
        });

        if (empty($requiredSteps)) {
            return 100;
        }

        $completed = count(array_intersect(array_column($requiredSteps, 'id'), $completedSteps));
        $total = count($requiredSteps);

        return (int) round(($completed / $total) * 100);
    }

    /**
     * Check if all required steps are completed.
     *
     * @return bool True if all required steps completed
     */
    public function areAllRequiredStepsCompleted()
    {
        $completedSteps = $this->getCompletedSteps();

        foreach ($this->steps as $stepId => $step) {
            if ($step['required'] && !in_array($stepId, $completedSteps)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Complete the wizard.
     *
     * @return bool True if successful
     */
    public function completeWizard()
    {
        if (!$this->areAllRequiredStepsCompleted()) {
            return false;
        }

        return $this->installationDetector->markWizardCompleted();
    }

    /**
     * Reset wizard progress (for testing).
     *
     * @return bool True if successful
     */
    public function resetWizard()
    {
        return $this->installationDetector->resetWizard();
    }

    // Step-specific completion checks

    /**
     * Check if organization info step is completed.
     *
     * @return bool
     */
    protected function isOrganizationInfoCompleted()
    {
        $setting = $this->settingGateway->getSettingByScope('SetupWizard', 'organization_info_completed');
        return $setting === 'Y';
    }

    /**
     * Check if admin account step is completed.
     *
     * @return bool
     */
    protected function isAdminAccountCompleted()
    {
        $setting = $this->settingGateway->getSettingByScope('SetupWizard', 'admin_account_completed');
        return $setting === 'Y';
    }

    /**
     * Check if operating hours step is completed.
     *
     * @return bool
     */
    protected function isOperatingHoursCompleted()
    {
        $setting = $this->settingGateway->getSettingByScope('SetupWizard', 'operating_hours_completed');
        return $setting === 'Y';
    }

    /**
     * Check if groups/rooms step is completed.
     *
     * @return bool
     */
    protected function isGroupsRoomsCompleted()
    {
        $setting = $this->settingGateway->getSettingByScope('SetupWizard', 'groups_rooms_completed');
        return $setting === 'Y';
    }

    /**
     * Check if finance settings step is completed.
     *
     * @return bool
     */
    protected function isFinanceSettingsCompleted()
    {
        $setting = $this->settingGateway->getSettingByScope('SetupWizard', 'finance_settings_completed');
        return $setting === 'Y';
    }

    /**
     * Check if service connectivity step is completed.
     *
     * @return bool
     */
    protected function isServiceConnectivityCompleted()
    {
        $setting = $this->settingGateway->getSettingByScope('SetupWizard', 'service_connectivity_completed');
        return $setting === 'Y';
    }

    /**
     * Check if sample data step is completed.
     *
     * @return bool
     */
    protected function isSampleDataCompleted()
    {
        $setting = $this->settingGateway->getSettingByScope('SetupWizard', 'sample_data_completed');
        return $setting === 'Y';
    }
}
