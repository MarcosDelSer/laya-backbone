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

namespace Gibbon\Module\NotificationEngine\Service;

use Gibbon\Module\NotificationEngine\Domain\NotificationGateway;

/**
 * PreferenceService
 *
 * Business logic for user notification preferences.
 * Handles email and push notification preference management
 * including default preference initialization and validation.
 *
 * Extracts preference handling business logic from NotificationGateway.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class PreferenceService
{
    /**
     * @var NotificationGateway
     */
    protected $notificationGateway;

    /**
     * Constructor.
     *
     * @param NotificationGateway $notificationGateway Notification gateway
     */
    public function __construct(NotificationGateway $notificationGateway)
    {
        $this->notificationGateway = $notificationGateway;
    }

    // =========================================================================
    // PREFERENCE RETRIEVAL
    // =========================================================================

    /**
     * Get all notification preferences for a user.
     *
     * Returns preferences for all notification types the user has configured.
     *
     * @param int $gibbonPersonID Person ID
     * @return array User preferences
     */
    public function getUserPreferences($gibbonPersonID)
    {
        return $this->notificationGateway->selectPreferencesByPerson($gibbonPersonID);
    }

    /**
     * Get preference for a specific notification type.
     *
     * @param int $gibbonPersonID Person ID
     * @param string $type Notification type
     * @return array|false Preference data or false if not found
     */
    public function getPreference($gibbonPersonID, $type)
    {
        return $this->notificationGateway->getPreference($gibbonPersonID, $type);
    }

    /**
     * Check if email notifications are enabled for a user and type.
     *
     * Defaults to enabled if no preference exists.
     *
     * @param int $gibbonPersonID Person ID
     * @param string $type Notification type
     * @return bool True if email enabled
     */
    public function isEmailEnabled($gibbonPersonID, $type)
    {
        return $this->notificationGateway->isEmailEnabled($gibbonPersonID, $type);
    }

    /**
     * Check if push notifications are enabled for a user and type.
     *
     * Defaults to enabled if no preference exists.
     *
     * @param int $gibbonPersonID Person ID
     * @param string $type Notification type
     * @return bool True if push enabled
     */
    public function isPushEnabled($gibbonPersonID, $type)
    {
        return $this->notificationGateway->isPushEnabled($gibbonPersonID, $type);
    }

    /**
     * Get effective notification channels for a user and type.
     *
     * Returns which channels are enabled (email, push, both, or none).
     *
     * @param int $gibbonPersonID Person ID
     * @param string $type Notification type
     * @return array Enabled channels with details
     */
    public function getEnabledChannels($gibbonPersonID, $type)
    {
        $emailEnabled = $this->isEmailEnabled($gibbonPersonID, $type);
        $pushEnabled = $this->isPushEnabled($gibbonPersonID, $type);

        $channels = [];
        if ($emailEnabled) {
            $channels[] = 'email';
        }
        if ($pushEnabled) {
            $channels[] = 'push';
        }

        return [
            'channels' => $channels,
            'emailEnabled' => $emailEnabled,
            'pushEnabled' => $pushEnabled,
            'hasAnyChannel' => !empty($channels),
        ];
    }

    // =========================================================================
    // PREFERENCE MANAGEMENT
    // =========================================================================

    /**
     * Set notification preference for a user and type.
     *
     * Creates or updates the preference record.
     *
     * @param int $gibbonPersonID Person ID
     * @param string $type Notification type
     * @param bool $emailEnabled Email enabled
     * @param bool $pushEnabled Push enabled
     * @return bool Success status
     */
    public function setPreference($gibbonPersonID, $type, $emailEnabled = true, $pushEnabled = true)
    {
        // Convert boolean to Y/N for database
        $emailEnabledValue = $emailEnabled ? 'Y' : 'N';
        $pushEnabledValue = $pushEnabled ? 'Y' : 'N';

        return $this->notificationGateway->setPreference(
            $gibbonPersonID,
            $type,
            $emailEnabledValue,
            $pushEnabledValue
        );
    }

    /**
     * Enable email notifications for a user and type.
     *
     * @param int $gibbonPersonID Person ID
     * @param string $type Notification type
     * @return bool Success status
     */
    public function enableEmail($gibbonPersonID, $type)
    {
        $currentPreference = $this->getPreference($gibbonPersonID, $type);
        $pushEnabled = $currentPreference ? ($currentPreference['pushEnabled'] === 'Y') : true;

        return $this->setPreference($gibbonPersonID, $type, true, $pushEnabled);
    }

    /**
     * Disable email notifications for a user and type.
     *
     * @param int $gibbonPersonID Person ID
     * @param string $type Notification type
     * @return bool Success status
     */
    public function disableEmail($gibbonPersonID, $type)
    {
        $currentPreference = $this->getPreference($gibbonPersonID, $type);
        $pushEnabled = $currentPreference ? ($currentPreference['pushEnabled'] === 'Y') : true;

        return $this->setPreference($gibbonPersonID, $type, false, $pushEnabled);
    }

    /**
     * Enable push notifications for a user and type.
     *
     * @param int $gibbonPersonID Person ID
     * @param string $type Notification type
     * @return bool Success status
     */
    public function enablePush($gibbonPersonID, $type)
    {
        $currentPreference = $this->getPreference($gibbonPersonID, $type);
        $emailEnabled = $currentPreference ? ($currentPreference['emailEnabled'] === 'Y') : true;

        return $this->setPreference($gibbonPersonID, $type, $emailEnabled, true);
    }

    /**
     * Disable push notifications for a user and type.
     *
     * @param int $gibbonPersonID Person ID
     * @param string $type Notification type
     * @return bool Success status
     */
    public function disablePush($gibbonPersonID, $type)
    {
        $currentPreference = $this->getPreference($gibbonPersonID, $type);
        $emailEnabled = $currentPreference ? ($currentPreference['emailEnabled'] === 'Y') : true;

        return $this->setPreference($gibbonPersonID, $type, $emailEnabled, false);
    }

    /**
     * Delete a user preference.
     *
     * Resets to default (all channels enabled).
     *
     * @param int $gibbonNotificationPreferenceID Preference ID
     * @return bool Success status
     */
    public function deletePreference($gibbonNotificationPreferenceID)
    {
        return $this->notificationGateway->deletePreference($gibbonNotificationPreferenceID);
    }

    /**
     * Reset all preferences for a user to defaults.
     *
     * Removes all custom preferences, restoring default behavior (all enabled).
     *
     * @param int $gibbonPersonID Person ID
     * @return int Number of preferences deleted
     */
    public function resetAllPreferences($gibbonPersonID)
    {
        $preferences = $this->getUserPreferences($gibbonPersonID);
        $count = 0;

        foreach ($preferences as $preference) {
            if ($this->deletePreference($preference['gibbonNotificationPreferenceID'])) {
                $count++;
            }
        }

        return $count;
    }

    // =========================================================================
    // BULK OPERATIONS
    // =========================================================================

    /**
     * Set preferences for multiple notification types at once.
     *
     * Useful for batch preference updates.
     *
     * @param int $gibbonPersonID Person ID
     * @param array $preferences Array of type => [emailEnabled, pushEnabled]
     * @return array Results with success/failure for each type
     */
    public function setBulkPreferences($gibbonPersonID, array $preferences)
    {
        $results = [];

        foreach ($preferences as $type => $settings) {
            $emailEnabled = $settings['emailEnabled'] ?? true;
            $pushEnabled = $settings['pushEnabled'] ?? true;

            $success = $this->setPreference($gibbonPersonID, $type, $emailEnabled, $pushEnabled);

            $results[$type] = [
                'success' => $success,
                'emailEnabled' => $emailEnabled,
                'pushEnabled' => $pushEnabled,
            ];
        }

        return $results;
    }

    /**
     * Disable all notifications for a user (across all types).
     *
     * Useful for user-requested notification opt-out.
     *
     * @param int $gibbonPersonID Person ID
     * @param array $types Notification types to disable (empty = all available)
     * @return int Number of preferences updated
     */
    public function disableAllNotifications($gibbonPersonID, array $types = [])
    {
        // If no types specified, get all active template types
        if (empty($types)) {
            $templates = $this->notificationGateway->selectActiveTemplates();
            $types = array_column($templates, 'type');
        }

        $count = 0;
        foreach ($types as $type) {
            if ($this->setPreference($gibbonPersonID, $type, false, false)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Enable all notifications for a user (across all types).
     *
     * Restores notification delivery after opt-out.
     *
     * @param int $gibbonPersonID Person ID
     * @param array $types Notification types to enable (empty = all available)
     * @return int Number of preferences updated
     */
    public function enableAllNotifications($gibbonPersonID, array $types = [])
    {
        // If no types specified, get all active template types
        if (empty($types)) {
            $templates = $this->notificationGateway->selectActiveTemplates();
            $types = array_column($templates, 'type');
        }

        $count = 0;
        foreach ($types as $type) {
            if ($this->setPreference($gibbonPersonID, $type, true, true)) {
                $count++;
            }
        }

        return $count;
    }

    // =========================================================================
    // VALIDATION
    // =========================================================================

    /**
     * Validate preference settings.
     *
     * Ensures at least one channel is enabled and type exists.
     *
     * @param string $type Notification type
     * @param bool $emailEnabled Email enabled flag
     * @param bool $pushEnabled Push enabled flag
     * @return array Validation result with isValid flag and errors
     */
    public function validatePreference($type, $emailEnabled, $pushEnabled)
    {
        $errors = [];

        // Check if type exists in templates
        $template = $this->notificationGateway->getTemplateByType($type);
        if (!$template) {
            $errors[] = "Invalid notification type: {$type}";
        }

        // Ensure at least one channel is enabled
        if (!$emailEnabled && !$pushEnabled) {
            $errors[] = 'At least one notification channel must be enabled';
        }

        return [
            'isValid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get preference summary for a user.
     *
     * Returns counts of enabled/disabled preferences by channel.
     *
     * @param int $gibbonPersonID Person ID
     * @return array Preference summary
     */
    public function getPreferenceSummary($gibbonPersonID)
    {
        $preferences = $this->getUserPreferences($gibbonPersonID);

        $summary = [
            'total' => count($preferences),
            'emailEnabled' => 0,
            'emailDisabled' => 0,
            'pushEnabled' => 0,
            'pushDisabled' => 0,
            'bothEnabled' => 0,
            'bothDisabled' => 0,
        ];

        foreach ($preferences as $pref) {
            $emailEnabled = $pref['emailEnabled'] === 'Y';
            $pushEnabled = $pref['pushEnabled'] === 'Y';

            if ($emailEnabled) {
                $summary['emailEnabled']++;
            } else {
                $summary['emailDisabled']++;
            }

            if ($pushEnabled) {
                $summary['pushEnabled']++;
            } else {
                $summary['pushDisabled']++;
            }

            if ($emailEnabled && $pushEnabled) {
                $summary['bothEnabled']++;
            } elseif (!$emailEnabled && !$pushEnabled) {
                $summary['bothDisabled']++;
            }
        }

        return $summary;
    }
}
