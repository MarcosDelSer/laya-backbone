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

use Gibbon\Module\NotificationEngine\Domain\PushDelivery;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\NotificationEngine\Domain\NotificationGateway;

/**
 * FCMService
 *
 * Firebase Cloud Messaging service facade.
 * Provides a clean API for FCM operations by wrapping PushDelivery.
 * Uses kreait/firebase-php:^7.0 for PHP 8.1+ compatibility.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class FCMService
{
    /**
     * @var PushDelivery
     */
    protected $pushDelivery;

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
        // Initialize the underlying PushDelivery service
        $this->pushDelivery = new PushDelivery($settingGateway, $notificationGateway);
    }

    /**
     * Check if FCM push notifications are enabled globally.
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->pushDelivery->isFCMEnabled();
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
    public function sendToDevice($deviceToken, $title, $body, $data = [])
    {
        return $this->pushDelivery->send($deviceToken, $title, $body, $data);
    }

    /**
     * Send a push notification to multiple devices (multicast).
     *
     * @param array $deviceTokens Array of FCM device tokens
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $data Additional data payload
     * @return array Results for each token
     */
    public function sendToDevices(array $deviceTokens, $title, $body, $data = [])
    {
        return $this->pushDelivery->sendMulticast($deviceTokens, $title, $body, $data);
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
        return $this->pushDelivery->sendToUser($gibbonPersonID, $type, $title, $body, $data);
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
        return $this->pushDelivery->sendFromTemplate($type, $gibbonPersonID, $templateData);
    }

    /**
     * Process and send a queued push notification.
     *
     * @param int $gibbonNotificationQueueID
     * @return array Result details
     */
    public function processQueuedNotification($gibbonNotificationQueueID)
    {
        return $this->pushDelivery->processQueuedNotification($gibbonNotificationQueueID);
    }

    /**
     * Validate a device token by sending a dry-run message.
     *
     * @param string $deviceToken
     * @return bool True if token is valid
     */
    public function validateToken($deviceToken)
    {
        return $this->pushDelivery->validateToken($deviceToken);
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
        return $this->pushDelivery->subscribeToTopic($topic, $deviceTokens);
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
        return $this->pushDelivery->unsubscribeFromTopic($topic, $deviceTokens);
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
        return $this->pushDelivery->sendToTopic($topic, $title, $body, $data);
    }

    /**
     * Check if push notifications can be sent to a recipient.
     *
     * @param int $gibbonPersonID Recipient person ID
     * @param string $type Notification type
     * @return bool
     */
    public function canSendToRecipient($gibbonPersonID, $type)
    {
        return $this->pushDelivery->canSendToRecipient($gibbonPersonID, $type);
    }

    /**
     * Get invalid tokens encountered during the last operation.
     *
     * @return array
     */
    public function getInvalidTokens()
    {
        return $this->pushDelivery->getInvalidTokens();
    }

    /**
     * Clear the invalid tokens list.
     *
     * @return void
     */
    public function clearInvalidTokens()
    {
        $this->pushDelivery->clearInvalidTokens();
    }

    /**
     * Get the last error details.
     *
     * @return array
     */
    public function getLastError()
    {
        return $this->pushDelivery->getLastError();
    }

    /**
     * Check if Firebase is properly configured and available.
     *
     * @return array Status information
     */
    public function getStatus()
    {
        return $this->pushDelivery->getStatus();
    }

    /**
     * Get the underlying PushDelivery instance.
     * Provides access to advanced features if needed.
     *
     * @return PushDelivery
     */
    public function getPushDelivery()
    {
        return $this->pushDelivery;
    }
}
