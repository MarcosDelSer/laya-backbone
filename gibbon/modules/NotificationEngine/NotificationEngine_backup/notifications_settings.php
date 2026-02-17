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

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Module\NotificationEngine\Domain\NotificationGateway;

if (isActionAccessible($guid, $connection2, '/modules/NotificationEngine/notifications_settings.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('Notification Settings'));

    // Get current user
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateway
    $notificationGateway = $container->get(NotificationGateway::class);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['preferences'])) {
        $preferences = $_POST['preferences'];
        $success = true;

        foreach ($preferences as $type => $settings) {
            $emailEnabled = isset($settings['email']) ? 'Y' : 'N';
            $pushEnabled = isset($settings['push']) ? 'Y' : 'N';

            $result = $notificationGateway->setPreference(
                $gibbonPersonID,
                $type,
                $emailEnabled,
                $pushEnabled
            );

            if (!$result) {
                $success = false;
            }
        }

        if ($success) {
            $page->addMessage(__('Your notification preferences have been saved.'));
        } else {
            $page->addError(__('There was an error saving your preferences. Please try again.'));
        }
    }

    // Get all notification templates (types)
    $templates = $notificationGateway->selectActiveTemplates();

    // Get user's current preferences
    $userPreferences = $notificationGateway->selectPreferencesByPerson($gibbonPersonID);
    $preferencesMap = [];
    foreach ($userPreferences as $pref) {
        $preferencesMap[$pref['type']] = $pref;
    }

    // Get user's registered devices
    $devices = $notificationGateway->selectActiveTokensByPerson($gibbonPersonID);
    $deviceStats = $notificationGateway->getDeviceStatistics($gibbonPersonID);

    // Display devices section
    echo '<h2>' . __('Registered Devices') . '</h2>';

    if (empty($devices)) {
        echo '<div class="warning">';
        echo '<strong>' . __('No devices registered') . '</strong><br>';
        echo __('To receive push notifications, please install the LAYA mobile app and log in. Your device will be registered automatically.');
        echo '</div>';
    } else {
        echo '<div class="message">';
        echo '<p>' . sprintf(__('You have %d registered device(s) for push notifications.'), count($devices)) . '</p>';
        echo '<ul>';
        foreach ($deviceStats as $stat) {
            $deviceType = ucfirst($stat['deviceType']);
            $count = $stat['count'];
            $lastUsed = $stat['lastUsed'] ? Format::dateTime($stat['lastUsed']) : __('Never');
            echo '<li>' . sprintf(__('%s: %d device(s), last used: %s'), $deviceType, $count, $lastUsed) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    // Display preferences form
    echo '<h2>' . __('Notification Preferences') . '</h2>';
    echo '<p>' . __('Choose which types of notifications you would like to receive and through which channels.') . '</p>';

    if (empty($templates)) {
        echo '<div class="message">' . __('No notification types are configured.') . '</div>';
    } else {
        $form = Form::create('notificationSettings', $session->get('absoluteURL') . '/index.php?q=/modules/NotificationEngine/notifications_settings.php');
        $form->setClass('w-full');

        // Create a table for preferences
        $form->addRow()->addHeading(__('Notification Types'), __('Configure your preferences for each notification type'));

        // Header row description
        $row = $form->addRow();
        $row->addContent('<div class="text-sm text-gray-600 mb-4">' .
            __('Check the boxes to enable notifications for each channel. Unchecked means you will not receive that notification type through that channel.') .
            '</div>');

        // Build preference rows for each notification type
        foreach ($templates as $template) {
            $type = $template['type'];
            $displayName = $template['nameDisplay'];

            // Get current preference or default to enabled
            $emailEnabled = true;
            $pushEnabled = true;

            if (isset($preferencesMap[$type])) {
                $emailEnabled = $preferencesMap[$type]['emailEnabled'] === 'Y';
                $pushEnabled = $preferencesMap[$type]['pushEnabled'] === 'Y';
            }

            $row = $form->addRow();
            $row->addLabel('pref_' . $type, $displayName)
                ->description(__('Receive notifications about') . ' ' . strtolower($displayName));

            $col = $row->addColumn()->addClass('flex gap-6 items-center');

            // Email checkbox
            $col->addContent('<label class="flex items-center gap-2">');
            $col->addCheckbox('preferences[' . $type . '][email]')
                ->setValue('Y')
                ->checked($emailEnabled);
            $col->addContent(__('Email') . '</label>');

            // Push checkbox
            $col->addContent('<label class="flex items-center gap-2">');
            $col->addCheckbox('preferences[' . $type . '][push]')
                ->setValue('Y')
                ->checked($pushEnabled);
            $col->addContent(__('Push') . '</label>');
        }

        // Submit button
        $row = $form->addRow();
        $row->addSubmit(__('Save Preferences'));

        echo $form->getOutput();
    }

    // Information about notification channels
    echo '<h2>' . __('About Notifications') . '</h2>';
    echo '<div class="message">';
    echo '<h4>' . __('Email Notifications') . '</h4>';
    echo '<p>' . __('Email notifications are sent to your registered email address. Make sure your email address is up to date in your profile.') . '</p>';

    echo '<h4>' . __('Push Notifications') . '</h4>';
    echo '<p>' . __('Push notifications are sent directly to your mobile device through the LAYA app. You need to:') . '</p>';
    echo '<ol>';
    echo '<li>' . __('Install the LAYA app on your iOS or Android device') . '</li>';
    echo '<li>' . __('Log in to the app with your LAYA account') . '</li>';
    echo '<li>' . __('Allow notifications when prompted') . '</li>';
    echo '</ol>';
    echo '<p>' . __('Once set up, you will receive instant notifications on your device.') . '</p>';
    echo '</div>';
}
