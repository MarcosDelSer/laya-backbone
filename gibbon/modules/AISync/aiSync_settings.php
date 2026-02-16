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

use Gibbon\Forms\Form;
use Gibbon\Domain\System\SettingGateway;

if (isActionAccessible($guid, $connection2, '/modules/AISync/aiSync_settings.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('AISync Settings'));

    // Get setting gateway
    $settingGateway = $container->get(SettingGateway::class);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $URL = $session->get('absoluteURL') . '/index.php?q=/modules/AISync/aiSync_settings.php';

        // Get form values
        $aiServiceURL = $_POST['aiServiceURL'] ?? '';
        $syncEnabled = $_POST['syncEnabled'] ?? 'N';
        $maxRetryAttempts = $_POST['maxRetryAttempts'] ?? '3';
        $retryDelaySeconds = $_POST['retryDelaySeconds'] ?? '30';
        $webhookTimeout = $_POST['webhookTimeout'] ?? '30';

        // Validate inputs
        $errors = [];

        if (empty($aiServiceURL)) {
            $errors[] = __('AI Service URL is required');
        } elseif (!filter_var($aiServiceURL, FILTER_VALIDATE_URL)) {
            $errors[] = __('AI Service URL must be a valid URL');
        }

        if (!is_numeric($maxRetryAttempts) || $maxRetryAttempts < 0 || $maxRetryAttempts > 10) {
            $errors[] = __('Max Retry Attempts must be a number between 0 and 10');
        }

        if (!is_numeric($retryDelaySeconds) || $retryDelaySeconds < 1 || $retryDelaySeconds > 3600) {
            $errors[] = __('Retry Delay must be a number between 1 and 3600 seconds');
        }

        if (!is_numeric($webhookTimeout) || $webhookTimeout < 1 || $webhookTimeout > 300) {
            $errors[] = __('Webhook Timeout must be a number between 1 and 300 seconds');
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $page->addError($error);
            }
        } else {
            // Save settings
            $success = true;

            $settings = [
                'aiServiceURL' => $aiServiceURL,
                'syncEnabled' => $syncEnabled,
                'maxRetryAttempts' => $maxRetryAttempts,
                'retryDelaySeconds' => $retryDelaySeconds,
                'webhookTimeout' => $webhookTimeout,
            ];

            foreach ($settings as $name => $value) {
                $result = $settingGateway->updateSettingByScope('AI Sync', $name, $value);
                if (!$result) {
                    $success = false;
                }
            }

            if ($success) {
                $page->addMessage(__('Settings saved successfully.'));
            } else {
                $page->addError(__('There was an error saving your settings. Please try again.'));
            }
        }
    }

    // Get current settings
    $aiServiceURL = $settingGateway->getSettingByScope('AI Sync', 'aiServiceURL') ?? 'http://ai-service:8000';
    $syncEnabled = $settingGateway->getSettingByScope('AI Sync', 'syncEnabled') ?? 'Y';
    $maxRetryAttempts = $settingGateway->getSettingByScope('AI Sync', 'maxRetryAttempts') ?? '3';
    $retryDelaySeconds = $settingGateway->getSettingByScope('AI Sync', 'retryDelaySeconds') ?? '30';
    $webhookTimeout = $settingGateway->getSettingByScope('AI Sync', 'webhookTimeout') ?? '30';

    // Display settings information
    echo '<p>' . __('Configure the connection settings and behavior for AI service synchronization.') . '</p>';

    // Create form
    $form = Form::create('aiSyncSettings', $session->get('absoluteURL') . '/index.php?q=/modules/AISync/aiSync_settings.php');
    $form->setClass('w-full');

    // Connection Settings Section
    $form->addRow()->addHeading(__('Connection Settings'));

    $row = $form->addRow();
    $row->addLabel('aiServiceURL', __('AI Service URL'))
        ->description(__('Base URL for the AI service API endpoint'));
    $row->addTextField('aiServiceURL')
        ->required()
        ->setValue($aiServiceURL)
        ->maxLength(255);

    $row = $form->addRow();
    $row->addLabel('webhookTimeout', __('Webhook Timeout (seconds)'))
        ->description(__('Maximum time to wait for webhook HTTP responses (1-300 seconds)'));
    $row->addNumber('webhookTimeout')
        ->required()
        ->minimum(1)
        ->maximum(300)
        ->setValue($webhookTimeout);

    // Sync Behavior Section
    $form->addRow()->addHeading(__('Sync Behavior'));

    $row = $form->addRow();
    $row->addLabel('syncEnabled', __('Enable Sync'))
        ->description(__('Enable or disable AI sync functionality globally'));
    $row->addYesNo('syncEnabled')
        ->selected($syncEnabled)
        ->required();

    $row = $form->addRow();
    $row->addLabel('maxRetryAttempts', __('Max Retry Attempts'))
        ->description(__('Maximum number of retry attempts for failed syncs (0-10)'));
    $row->addNumber('maxRetryAttempts')
        ->required()
        ->minimum(0)
        ->maximum(10)
        ->setValue($maxRetryAttempts);

    $row = $form->addRow();
    $row->addLabel('retryDelaySeconds', __('Base Retry Delay (seconds)'))
        ->description(__('Base delay in seconds before retrying (exponential backoff applied, 1-3600 seconds)'));
    $row->addNumber('retryDelaySeconds')
        ->required()
        ->minimum(1)
        ->maximum(3600)
        ->setValue($retryDelaySeconds);

    // Submit button
    $row = $form->addRow();
    $row->addSubmit(__('Save Settings'));

    echo $form->getOutput();

    // Information section
    echo '<h2>' . __('About AISync') . '</h2>';
    echo '<div class="message">';

    echo '<h4>' . __('How It Works') . '</h4>';
    echo '<p>' . __('AISync automatically synchronizes data from CareTracking and PhotoManagement to the AI service in real-time:') . '</p>';
    echo '<ul>';
    echo '<li>' . __('Activities, meals, naps, and attendance records are sent as webhooks') . '</li>';
    echo '<li>' . __('Photo uploads, tags, and deletions trigger synchronization events') . '</li>';
    echo '<li>' . __('Failed syncs are automatically retried with exponential backoff') . '</li>';
    echo '<li>' . __('Webhook health monitoring tracks success and failure rates') . '</li>';
    echo '</ul>';

    echo '<h4>' . __('Retry Logic') . '</h4>';
    echo '<p>' . __('When a webhook fails, AISync will automatically retry using exponential backoff:') . '</p>';
    echo '<ul>';
    echo '<li>' . sprintf(__('Attempt 1: %d seconds'), $retryDelaySeconds) . '</li>';
    echo '<li>' . sprintf(__('Attempt 2: %d seconds'), $retryDelaySeconds * 2) . '</li>';
    echo '<li>' . sprintf(__('Attempt 3: %d seconds'), $retryDelaySeconds * 4) . '</li>';
    echo '<li>' . __('And so on, up to the maximum retry attempts configured above') . '</li>';
    echo '</ul>';
    echo '<p>' . __('After reaching the maximum retry attempts, the sync is marked as permanently failed.') . '</p>';

    echo '<h4>' . __('Cron Job Setup') . '</h4>';
    echo '<p>' . __('To enable automatic retry processing, add this cron job to your server:') . '</p>';
    echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;">*/5 * * * * cd ' . realpath(__DIR__) . '/cli && php retryQueue.php</pre>';
    echo '<p>' . __('This will process failed syncs every 5 minutes.') . '</p>';

    echo '<h4>' . __('Manual Re-sync') . '</h4>';
    echo '<p>' . __('You can manually re-sync historical data using the CLI command:') . '</p>';
    echo '<pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto;">cd ' . realpath(__DIR__) . '/cli && php resync.php --type=activity --since=2026-02-01</pre>';
    echo '<p>' . __('See cli/README.md for complete documentation.') . '</p>';

    echo '<h4>' . __('Security') . '</h4>';
    echo '<p>' . __('All webhook calls are authenticated using JWT tokens. The AI service must be configured to accept tokens from this Gibbon instance.') . '</p>';

    echo '</div>';

    // Quick links
    echo '<h2>' . __('Quick Links') . '</h2>';
    echo '<div class="linkTop">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/AISync/aiSync_health.php">' . __('View Webhook Health Monitoring') . '</a>';
    echo '</div>';
    echo '<div class="linkTop">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/AISync/aiSync_logs.php">' . __('View Sync Logs') . '</a>';
    echo '</div>';
    echo '<div class="linkTop">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/AISync/aiSync_retry.php">' . __('Retry Failed Syncs') . '</a>';
    echo '</div>';
}
