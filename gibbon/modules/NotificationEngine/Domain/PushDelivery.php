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

use Gibbon\Domain\System\SettingGateway;

/**
 * PushDelivery
 *
 * Firebase Cloud Messaging (FCM) push notification delivery service.
 * Uses kreait/firebase-php:^7.0 (NOT v8.x which requires PHP 8.3+).
 * Supports single and multicast push notifications.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class PushDelivery
{
    /**
     * @var SettingGateway
     */
    protected $settingGateway;

    /**
     * @var NotificationGateway
     */
    protected $notificationGateway;

    /**
     * @var object|null Firebase Messaging instance
     */
    protected $messaging = null;

    /**
     * @var bool Whether Firebase is initialized
     */
    protected $initialized = false;

    /**
     * @var array Last error details
     */
    protected $lastError = [];

    /**
     * @var array Invalid tokens encountered during sending
     */
    protected $invalidTokens = [];

    /**
     * Constructor.
     *
     * @param SettingGateway $settingGateway Settings gateway
     * @param NotificationGateway $notificationGateway Notification gateway
     */
    public function __construct(
        SettingGateway $settingGateway,
        NotificationGateway $notificationGateway
    ) {
        $this->settingGateway = $settingGateway;
        $this->notificationGateway = $notificationGateway;
    }

    /**
     * Initialize Firebase Messaging.
     * Uses FIREBASE_CREDENTIALS_PATH environment variable for service account.
     *
     * @return bool True if initialized successfully
     */
    protected function initializeFirebase()
    {
        if ($this->initialized) {
            return true;
        }

        // Check if FCM is enabled
        if (!$this->isFCMEnabled()) {
            $this->lastError = [
                'code' => 'FCM_DISABLED',
                'message' => 'Firebase Cloud Messaging is disabled in settings',
            ];
            return false;
        }

        // Get credentials path from environment
        $credentialsPath = getenv('FIREBASE_CREDENTIALS_PATH');

        if (empty($credentialsPath)) {
            $this->lastError = [
                'code' => 'CREDENTIALS_MISSING',
                'message' => 'FIREBASE_CREDENTIALS_PATH environment variable is not set',
            ];
            return false;
        }

        if (!file_exists($credentialsPath)) {
            $this->lastError = [
                'code' => 'CREDENTIALS_NOT_FOUND',
                'message' => 'Firebase credentials file not found: ' . $credentialsPath,
            ];
            return false;
        }

        try {
            // Check if Firebase SDK is available
            if (!class_exists('Kreait\Firebase\Factory')) {
                $this->lastError = [
                    'code' => 'SDK_NOT_INSTALLED',
                    'message' => 'kreait/firebase-php package is not installed. Run: composer require kreait/firebase-php:^7.0',
                ];
                return false;
            }

            // Initialize Firebase using kreait/firebase-php v7.x
            $factory = (new \Kreait\Firebase\Factory())
                ->withServiceAccount($credentialsPath);

            $this->messaging = $factory->createMessaging();
            $this->initialized = true;

            return true;
        } catch (\Exception $e) {
            $this->lastError = [
                'code' => 'INIT_FAILED',
                'message' => 'Firebase initialization failed: ' . $e->getMessage(),
            ];
            return false;
        }
    }

    /**
     * Check if FCM push notifications are enabled globally.
     *
     * @return bool
     */
    public function isFCMEnabled()
    {
        $enabled = $this->settingGateway->getSettingByScope('Notification Engine', 'fcmEnabled');
        return $enabled === 'Y';
    }

    /**
     * Check if push notifications can be sent to a recipient.
     * Validates:
     * - FCM is enabled globally
     * - User has push enabled for this notification type
     * - User has active FCM tokens
     *
     * @param int $gibbonPersonID Recipient person ID
     * @param string $type Notification type
     * @return bool
     */
    public function canSendToRecipient($gibbonPersonID, $type)
    {
        // Check global setting
        if (!$this->isFCMEnabled()) {
            $this->lastError = [
                'code' => 'GLOBAL_DISABLED',
                'message' => 'Push notifications are disabled globally',
            ];
            return false;
        }

        // Check user's preference for this notification type
        if (!$this->notificationGateway->isPushEnabled($gibbonPersonID, $type)) {
            $this->lastError = [
                'code' => 'TYPE_DISABLED',
                'message' => "User has disabled push for notification type: {$type}",
            ];
            return false;
        }

        // Check if user has active tokens
        $tokens = $this->notificationGateway->selectActiveTokensByPerson($gibbonPersonID);
        if (empty($tokens)) {
            $this->lastError = [
                'code' => 'NO_TOKENS',
                'message' => 'User has no registered devices for push notifications',
            ];
            return false;
        }

        return true;
    }

    /**
     * Send a push notification to a single device.
     *
     * @param string $deviceToken FCM device token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return array Result with success/failure details
     */
    public function send($deviceToken, $title, $body, $data = [])
    {
        if (!$this->initializeFirebase()) {
            return [
                'success' => false,
                'error' => $this->lastError,
            ];
        }

        try {
            // Build the message using kreait/firebase-php v7.x API
            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $deviceToken)
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body));

            // Add data payload if provided
            if (!empty($data)) {
                // Ensure all data values are strings (FCM requirement)
                $stringData = [];
                foreach ($data as $key => $value) {
                    $stringData[$key] = is_string($value) ? $value : json_encode($value);
                }
                $message = $message->withData($stringData);
            }

            // Send the message
            $result = $this->messaging->send($message);

            // Update token last used timestamp
            $this->notificationGateway->updateTokenLastUsed($deviceToken);

            return [
                'success' => true,
                'message' => 'Push notification sent successfully',
                'messageId' => $result,
            ];
        } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
            // Token is invalid/expired - deactivate it
            $this->notificationGateway->deactivateToken($deviceToken);
            $this->invalidTokens[] = $deviceToken;

            return [
                'success' => false,
                'error' => [
                    'code' => 'TOKEN_NOT_FOUND',
                    'message' => 'Device token is invalid or expired',
                ],
            ];
        } catch (\Kreait\Firebase\Exception\Messaging\InvalidMessage $e) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'INVALID_MESSAGE',
                    'message' => 'Invalid message format: ' . $e->getMessage(),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'SEND_FAILED',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Send a push notification to multiple devices (multicast).
     * Uses FCM's multicast feature for efficient batch sending.
     *
     * @param array $deviceTokens Array of FCM device tokens
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return array Results for each token
     */
    public function sendMulticast(array $deviceTokens, $title, $body, $data = [])
    {
        if (!$this->initializeFirebase()) {
            return [
                'success' => false,
                'error' => $this->lastError,
            ];
        }

        if (empty($deviceTokens)) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'NO_TOKENS',
                    'message' => 'No device tokens provided',
                ],
            ];
        }

        try {
            // Build the message
            $message = \Kreait\Firebase\Messaging\CloudMessage::new()
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body));

            // Add data payload if provided
            if (!empty($data)) {
                $stringData = [];
                foreach ($data as $key => $value) {
                    $stringData[$key] = is_string($value) ? $value : json_encode($value);
                }
                $message = $message->withData($stringData);
            }

            // Send to multiple devices
            $sendReport = $this->messaging->sendMulticast($message, $deviceTokens);

            // Process results
            $successCount = $sendReport->successes()->count();
            $failureCount = $sendReport->failures()->count();

            // Handle failed tokens
            $failures = [];
            foreach ($sendReport->failures()->getItems() as $failure) {
                $token = $failure->target()->value();
                $error = $failure->error();

                // Check if token is invalid and should be deactivated
                if ($error instanceof \Kreait\Firebase\Exception\Messaging\NotFound ||
                    $error instanceof \Kreait\Firebase\Exception\Messaging\InvalidArgument) {
                    $this->notificationGateway->deactivateToken($token);
                    $this->invalidTokens[] = $token;
                }

                $failures[] = [
                    'token' => $token,
                    'error' => $error->getMessage(),
                ];
            }

            // Update last used timestamp for successful tokens
            foreach ($sendReport->successes()->getItems() as $success) {
                $token = $success->target()->value();
                $this->notificationGateway->updateTokenLastUsed($token);
            }

            return [
                'success' => $successCount > 0,
                'successCount' => $successCount,
                'failureCount' => $failureCount,
                'failures' => $failures,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'MULTICAST_FAILED',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Send a push notification to a user (all their active devices).
     *
     * @param int $gibbonPersonID Recipient person ID
     * @param string $type Notification type
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return array Result with success/failure details
     */
    public function sendToUser($gibbonPersonID, $type, $title, $body, $data = [])
    {
        // Check if we can send to this user
        if (!$this->canSendToRecipient($gibbonPersonID, $type)) {
            return [
                'success' => false,
                'skipped' => true,
                'error' => $this->lastError,
            ];
        }

        // Get user's active tokens
        $tokens = $this->notificationGateway->selectActiveTokensByPerson($gibbonPersonID);
        $deviceTokens = array_column($tokens, 'deviceToken');

        if (count($deviceTokens) === 1) {
            // Single device - use regular send
            return $this->send($deviceTokens[0], $title, $body, $data);
        }

        // Multiple devices - use multicast
        return $this->sendMulticast($deviceTokens, $title, $body, $data);
    }

    /**
     * Process and send a queued push notification.
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

        // Check channel - skip if not push or both
        if (!in_array($notification['channel'], ['push', 'both'])) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'Notification channel is not push',
            ];
        }

        // Get push title/body from template if available
        $title = $notification['title'];
        $body = $notification['body'];

        $template = $this->notificationGateway->getTemplateByType($notification['type']);
        if ($template && !empty($template['pushTitle'])) {
            // Parse payload data for template rendering
            $payloadData = [];
            if (!empty($notification['data'])) {
                $payloadData = is_string($notification['data'])
                    ? json_decode($notification['data'], true)
                    : $notification['data'];
            }
            $title = $this->notificationGateway->renderTemplate($template['pushTitle'], $payloadData ?: []);
            $body = $this->notificationGateway->renderTemplate($template['pushBody'] ?: $notification['body'], $payloadData ?: []);
        }

        // Build data payload
        $data = [
            'type' => $notification['type'],
            'notificationId' => (string) $gibbonNotificationQueueID,
        ];
        if (!empty($notification['data'])) {
            $extraData = is_string($notification['data'])
                ? json_decode($notification['data'], true)
                : $notification['data'];
            if (is_array($extraData)) {
                $data = array_merge($data, $extraData);
            }
        }

        // Send the push notification
        $result = $this->sendToUser(
            $notification['gibbonPersonID'],
            $notification['type'],
            $title,
            $body,
            $data
        );

        return $result;
    }

    /**
     * Send a notification from a template to a user.
     *
     * @param string $type Template type
     * @param int $gibbonPersonID Recipient
     * @param array $templateData Template placeholder values
     * @return array Result details
     */
    public function sendFromTemplate($type, $gibbonPersonID, array $templateData)
    {
        // Get the template
        $template = $this->notificationGateway->getTemplateByType($type);

        if (!$template || $template['active'] !== 'Y') {
            return [
                'success' => false,
                'error' => [
                    'code' => 'TEMPLATE_NOT_FOUND',
                    'message' => "No active template found for type: {$type}",
                ],
            ];
        }

        // Get push title and body, fall back to email subject/body if not set
        $title = !empty($template['pushTitle'])
            ? $this->notificationGateway->renderTemplate($template['pushTitle'], $templateData)
            : $this->notificationGateway->renderTemplate($template['subjectTemplate'], $templateData);

        $body = !empty($template['pushBody'])
            ? $this->notificationGateway->renderTemplate($template['pushBody'], $templateData)
            : strip_tags($this->notificationGateway->renderTemplate($template['bodyTemplate'], $templateData));

        // Truncate body for push notifications (FCM limit is ~4KB but best practice is shorter)
        if (strlen($body) > 200) {
            $body = substr($body, 0, 197) . '...';
        }

        return $this->sendToUser($gibbonPersonID, $type, $title, $body, $templateData);
    }

    /**
     * Get invalid tokens encountered during the last operation.
     *
     * @return array
     */
    public function getInvalidTokens()
    {
        return $this->invalidTokens;
    }

    /**
     * Clear the invalid tokens list.
     *
     * @return void
     */
    public function clearInvalidTokens()
    {
        $this->invalidTokens = [];
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
     * Validate a device token by sending a dry-run message.
     *
     * @param string $deviceToken
     * @return bool True if token is valid
     */
    public function validateToken($deviceToken)
    {
        if (!$this->initializeFirebase()) {
            return false;
        }

        try {
            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('token', $deviceToken)
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create('Test', 'Validation'));

            $this->messaging->validate($message);
            return true;
        } catch (\Exception $e) {
            $this->lastError = [
                'code' => 'VALIDATION_FAILED',
                'message' => $e->getMessage(),
            ];
            return false;
        }
    }

    /**
     * Subscribe a device token to a topic.
     *
     * @param string $topic Topic name
     * @param array $deviceTokens Device tokens to subscribe
     * @return array Result
     */
    public function subscribeToTopic($topic, array $deviceTokens)
    {
        if (!$this->initializeFirebase()) {
            return [
                'success' => false,
                'error' => $this->lastError,
            ];
        }

        try {
            $result = $this->messaging->subscribeToTopic($topic, $deviceTokens);
            return [
                'success' => true,
                'successCount' => count($deviceTokens) - count($result),
                'failures' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'SUBSCRIBE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Unsubscribe a device token from a topic.
     *
     * @param string $topic Topic name
     * @param array $deviceTokens Device tokens to unsubscribe
     * @return array Result
     */
    public function unsubscribeFromTopic($topic, array $deviceTokens)
    {
        if (!$this->initializeFirebase()) {
            return [
                'success' => false,
                'error' => $this->lastError,
            ];
        }

        try {
            $result = $this->messaging->unsubscribeFromTopic($topic, $deviceTokens);
            return [
                'success' => true,
                'successCount' => count($deviceTokens) - count($result),
                'failures' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'UNSUBSCRIBE_FAILED',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Send a notification to a topic.
     *
     * @param string $topic Topic name
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return array Result
     */
    public function sendToTopic($topic, $title, $body, $data = [])
    {
        if (!$this->initializeFirebase()) {
            return [
                'success' => false,
                'error' => $this->lastError,
            ];
        }

        try {
            $message = \Kreait\Firebase\Messaging\CloudMessage::withTarget('topic', $topic)
                ->withNotification(\Kreait\Firebase\Messaging\Notification::create($title, $body));

            if (!empty($data)) {
                $stringData = [];
                foreach ($data as $key => $value) {
                    $stringData[$key] = is_string($value) ? $value : json_encode($value);
                }
                $message = $message->withData($stringData);
            }

            $result = $this->messaging->send($message);

            return [
                'success' => true,
                'messageId' => $result,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'TOPIC_SEND_FAILED',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Check if Firebase is properly configured and available.
     *
     * @return array Status information
     */
    public function getStatus()
    {
        $status = [
            'enabled' => $this->isFCMEnabled(),
            'initialized' => $this->initialized,
            'sdkInstalled' => class_exists('Kreait\Firebase\Factory'),
            'credentialsPath' => getenv('FIREBASE_CREDENTIALS_PATH') ?: 'Not set',
            'credentialsExist' => false,
        ];

        if (!empty($status['credentialsPath']) && $status['credentialsPath'] !== 'Not set') {
            $status['credentialsExist'] = file_exists($status['credentialsPath']);
        }

        return $status;
    }
}
