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

namespace Gibbon\Module\NotificationEngine\Domain;

use Gibbon\Contracts\Comms\Mailer as MailerInterface;
use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\System\SettingGateway;

/**
 * EmailDelivery
 *
 * Email notification delivery service using Gibbon's Mailer.
 * Supports template rendering, recipient validation, and retry logic.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class EmailDelivery
{
    /**
     * @var MailerInterface
     */
    protected $mailer;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var SettingGateway
     */
    protected $settingGateway;

    /**
     * @var NotificationGateway
     */
    protected $notificationGateway;

    /**
     * @var array Last error details
     */
    protected $lastError = [];

    /**
     * Constructor.
     *
     * @param MailerInterface $mailer Gibbon Mailer instance
     * @param Session $session Gibbon Session
     * @param SettingGateway $settingGateway Settings gateway
     * @param NotificationGateway $notificationGateway Notification gateway
     */
    public function __construct(
        MailerInterface $mailer,
        Session $session,
        SettingGateway $settingGateway,
        NotificationGateway $notificationGateway
    ) {
        $this->mailer = $mailer;
        $this->session = $session;
        $this->settingGateway = $settingGateway;
        $this->notificationGateway = $notificationGateway;
    }

    /**
     * Check if email notifications are enabled globally.
     *
     * @return bool
     */
    public function isEmailEnabled()
    {
        $enabled = $this->settingGateway->getSettingByScope('Notification Engine', 'emailEnabled');
        return $enabled === 'Y';
    }

    /**
     * Check if a recipient can receive email notifications.
     * Validates:
     * - Global email notifications enabled
     * - User has receiveNotificationEmails = 'Y'
     * - User has valid email address
     * - User has email enabled for this notification type
     *
     * @param array $recipient Recipient data with email and preferences
     * @param string $type Notification type
     * @return bool
     */
    public function canSendToRecipient(array $recipient, $type)
    {
        // Check global setting
        if (!$this->isEmailEnabled()) {
            $this->lastError = [
                'code' => 'GLOBAL_DISABLED',
                'message' => 'Email notifications are disabled globally',
            ];
            return false;
        }

        // Check recipient email address
        if (empty($recipient['recipientEmail']) || !filter_var($recipient['recipientEmail'], FILTER_VALIDATE_EMAIL)) {
            $this->lastError = [
                'code' => 'INVALID_EMAIL',
                'message' => 'Recipient has no valid email address',
            ];
            return false;
        }

        // Check user's global email preference (receiveNotificationEmails)
        if (isset($recipient['receiveNotificationEmails']) && $recipient['receiveNotificationEmails'] === 'N') {
            $this->lastError = [
                'code' => 'USER_DISABLED',
                'message' => 'User has disabled email notifications',
            ];
            return false;
        }

        // Check user's preference for this notification type
        if (!$this->notificationGateway->isEmailEnabled($recipient['gibbonPersonID'], $type)) {
            $this->lastError = [
                'code' => 'TYPE_DISABLED',
                'message' => "User has disabled email for notification type: {$type}",
            ];
            return false;
        }

        return true;
    }

    /**
     * Send an email notification.
     *
     * @param array $notification Notification data from queue
     * @param array $recipient Recipient data
     * @return array Result with success/failure details
     */
    public function send(array $notification, array $recipient)
    {
        // Validate recipient can receive email
        if (!$this->canSendToRecipient($recipient, $notification['type'])) {
            return [
                'success' => false,
                'skipped' => true,
                'error' => $this->lastError,
            ];
        }

        try {
            // Get the school name for the from field
            $schoolName = $this->session->get('organisationName') ?: 'LAYA Kindergarten';
            $systemEmail = $this->session->get('organisationEmail');

            // Prepare the email
            $this->mailer->clearAll();

            // Set sender
            if ($systemEmail) {
                $this->mailer->SetFrom($systemEmail, $schoolName);
            }

            // Set recipient
            $recipientName = trim(($recipient['recipientPreferredName'] ?? '') . ' ' . ($recipient['recipientSurname'] ?? ''));
            $this->mailer->addAddress($recipient['recipientEmail'], $recipientName);

            // Set subject and body
            $this->mailer->setSubject($notification['title']);
            $this->mailer->setBody($this->formatEmailBody($notification, $recipient));

            // Send the email
            $sent = $this->mailer->Send();

            if ($sent) {
                return [
                    'success' => true,
                    'message' => 'Email sent successfully',
                    'recipient' => $recipient['recipientEmail'],
                ];
            } else {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'SEND_FAILED',
                        'message' => $this->mailer->ErrorInfo ?? 'Unknown mailer error',
                    ],
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'EXCEPTION',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Send a notification with retry logic and exponential backoff.
     *
     * @param array $notification Notification data from queue
     * @param array $recipient Recipient data
     * @param int $maxAttempts Maximum retry attempts (default: 3)
     * @return array Result with success/failure details
     */
    public function sendWithRetry(array $notification, array $recipient, $maxAttempts = 3)
    {
        $baseDelay = (int) ($this->settingGateway->getSettingByScope('Notification Engine', 'retryDelayMinutes') ?: 5);
        $attempt = 0;
        $lastResult = null;

        while ($attempt < $maxAttempts) {
            $attempt++;

            $result = $this->send($notification, $recipient);

            if ($result['success']) {
                return $result;
            }

            // If skipped (user preference), don't retry
            if (!empty($result['skipped'])) {
                return $result;
            }

            // Store last result for final return
            $lastResult = $result;

            // If not the last attempt, wait with exponential backoff
            if ($attempt < $maxAttempts) {
                $delaySeconds = $baseDelay * 60 * pow(2, $attempt - 1);
                // Cap at 1 hour
                $delaySeconds = min($delaySeconds, 3600);
                sleep($delaySeconds);
            }
        }

        // All attempts failed
        $lastResult['attempts'] = $attempt;
        return $lastResult;
    }

    /**
     * Format the email body for sending.
     * Wraps the notification body in a standard email template.
     *
     * @param array $notification Notification data
     * @param array $recipient Recipient data
     * @return string Formatted HTML email body
     */
    protected function formatEmailBody(array $notification, array $recipient)
    {
        $body = $notification['body'];
        $schoolName = $this->session->get('organisationName') ?: 'LAYA Kindergarten';
        $schoolWebsite = $this->session->get('organisationWebsite') ?: '';

        // Convert newlines to HTML breaks if body is plain text
        if (strpos($body, '<p>') === false && strpos($body, '<br') === false) {
            $body = nl2br(htmlspecialchars($body));
        }

        // Build simple HTML email
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($notification['title']) . '</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4A90A4; color: white; padding: 20px; text-align: center; }
        .content { background: #ffffff; padding: 30px; }
        .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .footer a { color: #4A90A4; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0; font-size: 24px;">' . htmlspecialchars($schoolName) . '</h1>
        </div>
        <div class="content">
            ' . $body . '
        </div>
        <div class="footer">
            <p>This email was sent by ' . htmlspecialchars($schoolName) . '</p>';

        if ($schoolWebsite) {
            $html .= '<p><a href="' . htmlspecialchars($schoolWebsite) . '">' . htmlspecialchars($schoolWebsite) . '</a></p>';
        }

        $html .= '
            <p style="margin-top: 15px;">To manage your notification preferences, please log in to your account.</p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Send a batch of notifications.
     * Processes multiple notifications efficiently.
     *
     * @param array $notifications Array of notification data
     * @return array Results for each notification
     */
    public function sendBatch(array $notifications)
    {
        $results = [];

        foreach ($notifications as $notification) {
            $recipient = [
                'gibbonPersonID' => $notification['gibbonPersonID'],
                'recipientEmail' => $notification['recipientEmail'],
                'recipientPreferredName' => $notification['recipientPreferredName'] ?? '',
                'recipientSurname' => $notification['recipientSurname'] ?? '',
                'receiveNotificationEmails' => $notification['receiveNotificationEmails'] ?? 'Y',
            ];

            $result = $this->send($notification, $recipient);
            $result['gibbonNotificationQueueID'] = $notification['gibbonNotificationQueueID'];
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Process and send a queued notification.
     * Updates queue status based on result.
     *
     * @param int $gibbonNotificationQueueID
     * @return array Result details
     */
    public function processQueuedNotification($gibbonNotificationQueueID)
    {
        // Get the notification from queue
        $notification = $this->notificationGateway->getNotificationByID($gibbonNotificationQueueID);

        if (!$notification) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Notification not found in queue',
                ],
            ];
        }

        // Mark as processing
        $this->notificationGateway->markProcessing($gibbonNotificationQueueID);

        // Build recipient data
        $recipient = [
            'gibbonPersonID' => $notification['gibbonPersonID'],
            'recipientEmail' => $notification['recipientEmail'],
            'recipientPreferredName' => $notification['recipientPreferredName'] ?? '',
            'recipientSurname' => $notification['recipientSurname'] ?? '',
            'receiveNotificationEmails' => $notification['receiveNotificationEmails'] ?? 'Y',
        ];

        // Get max attempts setting
        $maxAttempts = (int) ($this->settingGateway->getSettingByScope('Notification Engine', 'maxRetryAttempts') ?: 3);

        // Send the email
        $result = $this->send($notification, $recipient);

        // Update queue status based on result
        if ($result['success']) {
            $this->notificationGateway->markSent($gibbonNotificationQueueID);
        } elseif (!empty($result['skipped'])) {
            // Skipped due to user preference - mark as sent (don't retry)
            $this->notificationGateway->markSent($gibbonNotificationQueueID);
            $result['note'] = 'Skipped due to user preferences';
        } else {
            // Failed - update with error message
            $errorMessage = $result['error']['message'] ?? 'Unknown error';
            $this->notificationGateway->markFailed($gibbonNotificationQueueID, $errorMessage, $maxAttempts);
        }

        return $result;
    }

    /**
     * Get the last error details.
     *
     * @return array
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Create a notification from a template and queue it.
     *
     * @param string $type Notification type (matches template)
     * @param int $gibbonPersonID Recipient
     * @param array $templateData Template placeholder values
     * @param string $channel Delivery channel (email, push, both)
     * @return int|false Queued notification ID or false
     */
    public function queueFromTemplate($type, $gibbonPersonID, array $templateData, $channel = 'email')
    {
        // Get the template
        $template = $this->notificationGateway->getTemplateByType($type);

        if (!$template || $template['active'] !== 'Y') {
            $this->lastError = [
                'code' => 'TEMPLATE_NOT_FOUND',
                'message' => "No active template found for type: {$type}",
            ];
            return false;
        }

        // Render the template
        $title = $this->notificationGateway->renderTemplate($template['subjectTemplate'], $templateData);
        $body = $this->notificationGateway->renderTemplate($template['bodyTemplate'], $templateData);

        // Queue the notification
        return $this->notificationGateway->insertNotification([
            'gibbonPersonID' => $gibbonPersonID,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'data' => $templateData,
            'channel' => $channel,
        ]);
    }

    /**
     * Send an immediate email (bypass queue).
     * Use for critical notifications that must be sent immediately.
     *
     * @param string $email Recipient email
     * @param string $name Recipient name
     * @param string $subject Email subject
     * @param string $body Email body
     * @return bool Success
     */
    public function sendImmediate($email, $name, $subject, $body)
    {
        if (!$this->isEmailEnabled()) {
            return false;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        try {
            $schoolName = $this->session->get('organisationName') ?: 'LAYA Kindergarten';
            $systemEmail = $this->session->get('organisationEmail');

            $this->mailer->clearAll();

            if ($systemEmail) {
                $this->mailer->SetFrom($systemEmail, $schoolName);
            }

            $this->mailer->addAddress($email, $name);
            $this->mailer->setSubject($subject);

            // Format body as HTML if it's plain text
            if (strpos($body, '<p>') === false && strpos($body, '<br') === false) {
                $body = nl2br(htmlspecialchars($body));
            }

            $this->mailer->setBody($body);

            return $this->mailer->Send();
        } catch (\Exception $e) {
            $this->lastError = [
                'code' => 'EXCEPTION',
                'message' => $e->getMessage(),
            ];
            return false;
        }
    }
}
